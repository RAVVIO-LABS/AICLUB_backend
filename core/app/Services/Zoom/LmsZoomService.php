<?php

namespace App\Services\Zoom;

use App\Models\Course;
use App\Models\CourseLiveBatch;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveSession;
use App\Models\CourseZoomMeeting;
use App\Models\CourseZoomMeetingOccurrence;
use App\Models\ZoomAuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LmsZoomService
{
    private const TOKEN_CACHE_KEY = 'zoom_s2s_access_token';
    private const START_REDIRECT_CACHE_PREFIX = 'zoom_start_redirect:';

    private array $hostUpcomingMeetingsCache = [];

    public function accessToken(): string
    {
        $this->ensureConfigured();

        $cached = cache()->get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $response = Http::asForm()->withBasicAuth(config('zoom.client_id'), config('zoom.client_secret'))->post('https://zoom.us/oauth/token', [
            'grant_type' => 'account_credentials',
            'account_id' => config('zoom.account_id'),
        ]);

        if (!$response->successful()) {
            $message = $response->json('reason') ?: $response->json('error_description') ?: $response->json('message') ?: 'Unable to obtain Zoom access token';
            throw new \RuntimeException('Unable to obtain Zoom access token: ' . $message);
        }

        $token = $response->json('access_token');
        $ttl = max(((int) $response->json('expires_in', 3600)) - 60, 60);

        cache()->put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds($ttl));

        return $token;
    }

    public function allowedHosts(): array
    {
        return config('zoom.host_pool', []);
    }

    public function healthReport(): array
    {
        $report = [
            'configured' => true,
            'oauth' => [
                'ok' => false,
                'message' => null,
            ],
            'hosts' => [],
        ];

        try {
            $token = $this->accessToken();
            $report['oauth']['ok'] = !empty($token);
            $report['oauth']['message'] = $report['oauth']['ok'] ? 'Access token generated successfully' : 'Access token is empty';
        } catch (\Throwable $throwable) {
            $report['oauth']['ok'] = false;
            $report['oauth']['message'] = $throwable->getMessage();
            return $report;
        }

        foreach ($this->allowedHosts() as $host) {
            $email = (string) ($host['email'] ?? '');
            $label = (string) ($host['label'] ?? $email);

            if (!$email) {
                $report['hosts'][] = [
                    'label' => $label ?: 'Unknown',
                    'email' => $email,
                    'ok' => false,
                    'message' => 'Missing host email in pool configuration',
                    'upcoming_count' => null,
                ];
                continue;
            }

            try {
                $meetings = $this->upcomingMeetings($email);
                $report['hosts'][] = [
                    'label' => $label,
                    'email' => $email,
                    'ok' => true,
                    'message' => 'Host is accessible',
                    'upcoming_count' => count($meetings),
                ];
            } catch (\Throwable $throwable) {
                $report['hosts'][] = [
                    'label' => $label,
                    'email' => $email,
                    'ok' => false,
                    'message' => $throwable->getMessage(),
                    'upcoming_count' => null,
                ];
            }
        }

        return $report;
    }

    public function selectHost(Carbon $startAt, Carbon $endAt): ?array
    {
        return $this->selectHostForSlot($startAt, $endAt);
    }

    public function assertCourseBookingAvailable(Course $course, string $startDate, string $startTime, int $durationMinutes, array $lectureSchedule = []): void
    {
        $startAt = Carbon::parse($startDate . ' ' . $startTime, $this->meetingTimezone());

        if (empty($lectureSchedule)) {
            $endAt = $startAt->copy()->addMinutes($durationMinutes);
            if (!$this->selectHost($startAt, $endAt)) {
                throw new \RuntimeException('No zoom available for this time.');
            }

            return;
        }

        $this->buildSessionPlans($course, $startAt, $durationMinutes, null, $lectureSchedule);
    }

    private function selectHostForSlot(Carbon $startAt, Carbon $endAt, array $plannedSlots = []): ?array
    {
        foreach ($this->allowedHosts() as $host) {
            if (!$this->plannedHostHasConflict($host['email'], $startAt, $endAt, $plannedSlots) && !$this->hostHasConflict($host['email'], $startAt, $endAt)) {
                return $host;
            }
        }

        return null;
    }

    public function hostHasConflict(string $hostEmail, Carbon $startAt, Carbon $endAt): bool
    {
        $start = $startAt->copy()->utc();
        $end = $endAt->copy()->utc();

        $localConflict = CourseLiveSession::where('zoom_host_email', $hostEmail)
            ->whereIn('status', ['scheduled', 'assigned', 'started'])
            ->get()
            ->contains(function ($session) use ($start, $end) {
                if (!$session->scheduled_at || !$session->duration_minutes) {
                    return false;
                }

                $existingStart = Carbon::parse($session->scheduled_at)->utc();
                $existingEnd = $existingStart->copy()->addMinutes((int) $session->duration_minutes);

                return $start->lt($existingEnd) && $existingStart->lt($end);
            });

        if ($localConflict) {
            return true;
        }

        try {
            foreach ($this->upcomingMeetings($hostEmail) as $meeting) {
                if (empty($meeting['start_time']) || empty($meeting['duration'])) {
                    continue;
                }

                $meetingStart = Carbon::parse($meeting['start_time'])->utc();
                $meetingEnd = $meetingStart->copy()->addMinutes((int) $meeting['duration']);

                if ($start->lt($meetingEnd) && $meetingStart->lt($end)) {
                    return true;
                }
            }
        } catch (\Throwable $throwable) {
            Log::warning('Zoom conflict check failed', ['host' => $hostEmail, 'error' => $throwable->getMessage()]);

            if (str_contains($throwable->getMessage(), 'Unable to obtain Zoom access token')) {
                throw $throwable;
            }

            return false;
        }

        return false;
    }

    public function createMeeting(array $payload): array
    {
        $requestPayload = [
            'topic' => $payload['meeting_name'],
            'type' => (int) ($payload['type'] ?? 2),
            'start_time' => $payload['start_time']->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration' => $payload['duration_minutes'],
            'timezone' => $payload['timezone'] ?? $this->meetingTimezone(),
            'agenda' => $payload['agenda'] ?? $payload['meeting_name'],
            'settings' => [
                'join_before_host' => config('zoom.meeting.join_before_host', false),
                'waiting_room' => config('zoom.meeting.waiting_room', true),
                'auto_recording' => config('zoom.meeting.auto_recording', 'cloud'),
            ],
        ];

        if (!empty($payload['recurrence']) && is_array($payload['recurrence'])) {
            $requestPayload['recurrence'] = $payload['recurrence'];
        }

        $response = Http::withToken($this->accessToken())->post(
            'https://api.zoom.us/v2/users/' . urlencode($payload['host_email']) . '/meetings',
            $requestPayload
        );

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('message', 'Unable to create Zoom meeting'));
        }

        return $response->json();
    }

    public function updateMeeting(string|int $meetingId, array $payload): array
    {
        $requestPayload = [
            'topic' => $payload['meeting_name'],
            'type' => (int) ($payload['type'] ?? 2),
            'start_time' => $payload['start_time']->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration' => $payload['duration_minutes'],
            'timezone' => $payload['timezone'] ?? $this->meetingTimezone(),
            'agenda' => $payload['agenda'] ?? $payload['meeting_name'],
            'settings' => [
                'join_before_host' => config('zoom.meeting.join_before_host', false),
                'waiting_room' => config('zoom.meeting.waiting_room', true),
                'auto_recording' => config('zoom.meeting.auto_recording', 'cloud'),
            ],
        ];

        if (!empty($payload['recurrence']) && is_array($payload['recurrence'])) {
            $requestPayload['recurrence'] = $payload['recurrence'];
        }

        $response = Http::withToken($this->accessToken())->patch(
            'https://api.zoom.us/v2/meetings/' . $meetingId,
            $requestPayload
        );

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('message', 'Unable to update Zoom meeting'));
        }

        // Do not fetch meeting details here, because many S2S apps are granted
        // update scopes without read scopes, and a follow-up GET can fail with:
        // "Invalid access token, does not contain scope".
        return [
            'id' => (string) $meetingId,
        ];
    }

    public function meetingDetails(string|int $meetingId): array
    {
        $response = Http::withToken($this->accessToken())->get('https://api.zoom.us/v2/meetings/' . $meetingId);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('message', 'Unable to fetch Zoom meeting'));
        }

        return $response->json();
    }

    public function freshStartUrl(string|int $meetingId): string
    {
        return $this->meetingDetails($meetingId)['start_url'] ?? '';
    }

    public function createStartRedirectToken(CourseLiveSession $session, int $teacherId): string
    {
        $token = bin2hex(random_bytes(32));

        Cache::put(self::START_REDIRECT_CACHE_PREFIX . $token, [
            'session_id' => $session->id,
            'teacher_id' => $teacherId,
        ], now()->addMinute());

        return $token;
    }

    public function consumeStartRedirectToken(string $token): ?array
    {
        $key = self::START_REDIRECT_CACHE_PREFIX . $token;
        $payload = Cache::pull($key);

        return is_array($payload) ? $payload : null;
    }

    public function deleteMeeting(string|int|null $meetingId): void
    {
        if (!$meetingId) {
            return;
        }

        $response = Http::withToken($this->accessToken())->delete('https://api.zoom.us/v2/meetings/' . $meetingId);

        if (!$response->successful() && $response->status() !== 404) {
            throw new \RuntimeException($response->json('message', 'Unable to delete Zoom meeting'));
        }
    }

    public function log(string $action, array $context = []): void
    {
        ZoomAuditLog::create([
            'course_id' => $context['course_id'] ?? null,
            'batch_id' => $context['batch_id'] ?? null,
            'live_session_id' => $context['live_session_id'] ?? null,
            'actor_type' => $context['actor_type'] ?? null,
            'actor_id' => $context['actor_id'] ?? null,
            'action' => $action,
            'meta' => $context['meta'] ?? [],
        ]);
    }

    public function generateBatchSessions(Course $course, CourseLiveBatch $batch, array $lectureSchedule = []): array
    {
        $startAt = Carbon::parse($batch->start_date . ' ' . $batch->meeting_start_time, $this->meetingTimezone());
        $durationMinutes = (int) ($batch->class_duration ?: $course->default_class_duration ?: $course->meeting_duration ?: 60);
        $sessions = [];
        $plans = $this->buildSessionPlans($course, $startAt, $durationMinutes, $this->liveLecturesForCourse($course, $batch->lecture_ids), $lectureSchedule);

        foreach ($plans as $plan) {
            $index = $plan['session_number'];
            $scheduledAt = $plan['scheduled_at'];
            $host = $plan['host'];
            $lecture = $plan['lecture'];
            $meetingName = $this->buildMeetingName($course->title, $scheduledAt, $index);
            $meeting = $this->createMeeting([
                'host_email' => $host['email'],
                'meeting_name' => $meetingName,
                'start_time' => $scheduledAt,
                'duration_minutes' => $durationMinutes,
                'agenda' => $course->title,
            ]);

            $session = CourseLiveSession::create([
                'course_id' => $course->id,
                'course_lecture_id' => $lecture->id,
                'batch_id' => $batch->id,
                'session_number' => $index,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $durationMinutes,
                'meeting_name' => $meetingName,
                'zoom_meeting_id' => $meeting['id'] ?? null,
                'zoom_join_url' => $meeting['join_url'] ?? null,
                'zoom_host_email' => $host['email'],
                'zoom_host_user_id' => $meeting['host_id'] ?? $host['email'],
                'zoom_subaccount_id' => null,
                'recording_status' => 'cloud',
                'status' => 'scheduled',
            ]);

            $sessions[] = $session;

            $this->log('created', [
                'course_id' => $course->id,
                'batch_id' => $batch->id,
                'live_session_id' => $session->id,
                'actor_type' => 'system',
                'meta' => [
                    'meeting_id' => $meeting['id'] ?? null,
                    'host_email' => $host['email'],
                    'scheduled_at' => (string) $scheduledAt,
                ],
            ]);
        }

        return $sessions;
    }

    public function generateBookingSessions(Course $course, CourseLiveBooking $booking, array $lectureSchedule = [], ?array $recurrence = null): array
    {
        if ($booking->class_type !== 'one_to_one') {
            return [];
        }

        $startAt = Carbon::parse($booking->start_date . ' ' . $booking->start_time, $this->meetingTimezone());
        $durationMinutes = (int) ($booking->class_duration ?: $course->default_class_duration ?: $course->meeting_duration ?: 60);
        $sessions = [];
        $rec = is_array($recurrence) ? $recurrence : [];
        $starts = $this->buildStandaloneOccurrenceStarts($startAt, $rec);
        if (empty($starts)) {
            $starts = [$startAt->copy()];
        }

        $firstStart = $starts[0];
        $endAt = $firstStart->copy()->addMinutes($durationMinutes);
        $host = $this->selectHost($firstStart, $endAt);
        if (!$host || empty($host['email'])) {
            throw new \RuntimeException('No zoom available for this time.');
        }

        $meetingName = $this->buildMeetingName($course->title, $firstStart, 1);
        $meeting = new CourseZoomMeeting();
        $meeting->course_id = (int) $course->id;
        $meeting->instructor_id = (int) ($booking->course->instructor_id ?? $course->instructor_id ?? 0);
        $meeting->batch_id = null;
        $meeting->topic = (string) $meetingName;
        $meeting->description = (string) $course->title;
        $meeting->start_time = $firstStart->toDateTimeString();
        $meeting->duration_minutes = (int) $durationMinutes;
        $meeting->timezone = $this->meetingTimezone();
        $meeting->recurrence = $rec;
        $meeting->status = 'scheduled';
        $meeting->save();

        try {
            $zoomMeeting = $this->createMeeting([
                'host_email' => $host['email'],
                'meeting_name' => (string) ($meeting->topic ?: $course->title),
                'start_time' => $firstStart,
                'duration_minutes' => (int) $meeting->duration_minutes,
                'agenda' => (string) $meeting->description,
            ]);

            $meeting->zoom_meeting_id = (string) ($zoomMeeting['id'] ?? '');
            $meeting->zoom_join_url = $zoomMeeting['join_url'] ?? null;
            $meeting->zoom_host_email = $host['email'];
            $meeting->zoom_host_user_id = $zoomMeeting['host_id'] ?? $host['email'];
            $meeting->save();
        } catch (\Throwable $throwable) {
            CourseZoomMeetingOccurrence::where('course_zoom_meeting_id', $meeting->id)->delete();
            $meeting->delete();
            throw $throwable;
        }

        foreach ($starts as $index => $occurrenceStart) {
            CourseZoomMeetingOccurrence::create([
                'course_zoom_meeting_id' => (int) $meeting->id,
                'teacher_instructor_id' => $booking->assigned_teacher_id ? (int) $booking->assigned_teacher_id : null,
                'title' => $meeting->topic,
                'start_time' => $occurrenceStart->toDateTimeString(),
                'duration_minutes' => $durationMinutes,
                'zoom_join_url' => $meeting->zoom_join_url,
                'status' => 'scheduled',
            ]);

            $session = CourseLiveSession::create([
                'course_id' => $course->id,
                'course_lecture_id' => null,
                'batch_id' => null,
                'course_purchased_id' => $booking->course_purchased_id,
                'user_id' => $booking->user_id,
                'assigned_teacher_id' => $booking->assigned_teacher_id,
                'session_number' => $index + 1,
                'scheduled_at' => $occurrenceStart,
                'duration_minutes' => $durationMinutes,
                'meeting_name' => $meeting->topic,
                'zoom_meeting_id' => $meeting->zoom_meeting_id ?? null,
                'zoom_join_url' => $meeting->zoom_join_url ?? null,
                'zoom_host_email' => $meeting->zoom_host_email ?? $host['email'],
                'zoom_host_user_id' => $meeting->zoom_host_user_id ?? $host['email'],
                'zoom_subaccount_id' => null,
                'recording_status' => 'cloud',
                'status' => 'scheduled',
            ]);

            $sessions[] = $session;

            $this->log('created', [
                'course_id' => $course->id,
                'batch_id' => null,
                'live_session_id' => $session->id,
                'actor_type' => 'system',
                'meta' => [
                    'meeting_id' => $meeting->zoom_meeting_id ?? null,
                    'host_email' => $meeting->zoom_host_email ?? $host['email'],
                    'scheduled_at' => (string) $occurrenceStart,
                    'booking_id' => $booking->id,
                    'course_zoom_meeting_id' => $meeting->id,
                ],
            ]);
        }

        return $sessions;
    }

    public function syncTeacherToSessions(int $courseId, ?int $batchId, int $teacherId, ?int $actorId = null): void
    {
        $sessions = CourseLiveSession::where('course_id', $courseId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId), fn ($query) => $query->whereNull('batch_id'))
            ->get();

        foreach ($sessions as $session) {
            $session->assigned_teacher_id = $teacherId;
            $session->status = $session->status === 'scheduled' ? 'assigned' : $session->status;
            $session->save();

            $this->log('updated', [
                'course_id' => $session->course_id,
                'batch_id' => $session->batch_id,
                'live_session_id' => $session->id,
                'actor_type' => 'admin',
                'actor_id' => $actorId,
                'meta' => [
                    'assigned_teacher_id' => $teacherId,
                ],
            ]);
        }
    }

    public function syncTeacherToBookingSessions(CourseLiveBooking $booking, int $teacherId, ?int $actorId = null): void
    {
        $sessions = CourseLiveSession::where('course_purchased_id', $booking->course_purchased_id)
            ->where('user_id', $booking->user_id)
            ->get();

        foreach ($sessions as $session) {
            $session->assigned_teacher_id = $teacherId;
            $session->status = $session->status === 'scheduled' ? 'assigned' : $session->status;
            $session->save();

            $this->log('updated', [
                'course_id' => $session->course_id,
                'batch_id' => $session->batch_id,
                'live_session_id' => $session->id,
                'actor_type' => 'admin',
                'actor_id' => $actorId,
                'meta' => [
                    'assigned_teacher_id' => $teacherId,
                    'booking_id' => $booking->id,
                ],
            ]);
        }
    }

    public function markStarted(CourseLiveSession $session, int $teacherId): void
    {
        $session->status = 'started';
        $session->save();

        $this->log('started', [
            'course_id' => $session->course_id,
            'batch_id' => $session->batch_id,
            'live_session_id' => $session->id,
            'actor_type' => 'teacher',
            'actor_id' => $teacherId,
            'meta' => [
                'meeting_id' => $session->zoom_meeting_id,
                'teacher_id' => $teacherId,
            ],
        ]);
    }

    public function deleteSessionsForBatch(CourseLiveBatch $batch, ?int $actorId = null, string $actorType = 'system'): void
    {
        $this->deleteSessions(
            CourseLiveSession::where('batch_id', $batch->id),
            $actorType,
            $actorId
        );
    }

    public function deleteSessionsForBooking(CourseLiveBooking $booking, ?int $actorId = null, string $actorType = 'system'): void
    {
        $this->deleteSessions(
            CourseLiveSession::where('course_purchased_id', $booking->course_purchased_id)->where('user_id', $booking->user_id),
            $actorType,
            $actorId
        );
    }

    public function buildMeetingName(string $courseName, Carbon $scheduledAt, int $sessionNumber): string
    {
        $normalizedCourseName = preg_replace('/[^A-Za-z0-9]+/', '', $courseName) ?: 'Course';

        return $normalizedCourseName . '_' . $scheduledAt->format('Y-m-d') . '_session_' . $sessionNumber;
    }

    private function buildStandaloneOccurrenceStarts(Carbon $startAt, array $recurrence): array
    {
        $type = strtolower((string) ($recurrence['label_type'] ?? ($recurrence['type'] ?? 'none')));
        $interval = max(1, (int) ($recurrence['repeat_interval'] ?? 1));
        $endMode = strtolower((string) ($recurrence['end_mode'] ?? 'none'));
        $endTimes = max(1, (int) ($recurrence['end_times'] ?? 1));
        $endDate = !empty($recurrence['end_date_time']) ? Carbon::parse($recurrence['end_date_time']) : null;

        $weeklyDays = collect(explode(',', (string) ($recurrence['weekly_days'] ?? '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();

        $starts = [];

        if ($type === 'none') {
            return [$startAt->copy()];
        }

        if ($type === 'daily') {
            $cursor = $startAt->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : max(60, $endTimes);
            while (count($starts) < $limit) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $starts[] = $cursor->copy();
                $cursor->addDays($interval);
            }

            return $starts;
        }

        if ($type === 'weekly') {
            if (empty($weeklyDays)) {
                $weeklyDays = [((int) $startAt->dayOfWeek) + 1];
            }

            $cursor = $startAt->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : max(120, $endTimes);
            while (count($starts) < $limit) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $weekDiff = (int) floor($startAt->copy()->startOfWeek()->diffInDays($cursor->copy()->startOfWeek()) / 7);
                $inIntervalWeek = $weekDiff % $interval === 0;
                $dayCode = ((int) $cursor->dayOfWeek) + 1;

                if ($inIntervalWeek && in_array($dayCode, $weeklyDays, true) && $cursor->gte($startAt)) {
                    $starts[] = $cursor->copy();
                }

                $cursor->addDay();
            }

            return $starts;
        }

        $cursor = $startAt->copy();
        $limit = $endMode === 'occurrences' ? $endTimes : max(24, $endTimes);
        while (count($starts) < $limit) {
            if ($endDate && $cursor->gt($endDate)) {
                break;
            }

            $starts[] = $cursor->copy();
            $cursor->addMonthsNoOverflow($interval);
        }

        return $starts;
    }

    private function buildSessionPlans(Course $course, Carbon $startAt, int $durationMinutes, ?iterable $lectures = null, array $lectureSchedule = []): array
    {
        $plans = [];
        $lectures = collect($lectures ?: $this->liveLecturesForCourse($course))->values();
        if ($lectures->isEmpty()) {
            $lectureIdsFromSchedule = collect(array_keys($lectureSchedule))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();
            $lectures = collect($this->allLecturesForCourse($course, $lectureIdsFromSchedule))->values();
        }
        $timeTemplate = $startAt->format('H:i:s');

        if ($lectures->isEmpty()) {
            throw new \RuntimeException('Please add at least one lecture before scheduling Zoom.');
        }

        foreach ($lectures as $offset => $lecture) {
            $index = $offset + 1;
            $lectureDate = $lectureSchedule[(int) $lecture->id] ?? null;

            if ($lectureDate) {
                $lectureDateString = trim((string) $lectureDate);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $lectureDateString)) {
                    $scheduledAt = Carbon::parse($lectureDateString . ' ' . $timeTemplate, $this->meetingTimezone());
                } else {
                    $scheduledAt = Carbon::parse($lectureDateString, $this->meetingTimezone());
                }
            } else {
                $scheduledAt = $startAt->copy()->addDays($index - 1);
            }

            $endAt = $scheduledAt->copy()->addMinutes($durationMinutes);
            $host = $this->selectHostForSlot($scheduledAt, $endAt, $plans);

            if (!$host) {
                throw new \RuntimeException('No zoom available for this time.');
            }

            $plans[] = [
                'course_id' => $course->id,
                'course_lecture_id' => $lecture->id,
                'lecture' => $lecture,
                'session_number' => $index,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $durationMinutes,
                'host' => $host,
            ];
        }

        return $plans;
    }

    private function plannedHostHasConflict(string $hostEmail, Carbon $startAt, Carbon $endAt, array $plannedSlots): bool
    {
        foreach ($plannedSlots as $slot) {
            if (($slot['host']['email'] ?? null) !== $hostEmail) {
                continue;
            }

            $existingStart = $slot['scheduled_at']->copy()->utc();
            $existingEnd = $existingStart->copy()->addMinutes((int) $slot['duration_minutes']);

            if ($startAt->copy()->utc()->lt($existingEnd) && $existingStart->lt($endAt->copy()->utc())) {
                return true;
            }
        }

        return false;
    }

    private function upcomingMeetings(string $hostEmail): array
    {
        if (array_key_exists($hostEmail, $this->hostUpcomingMeetingsCache)) {
            return $this->hostUpcomingMeetingsCache[$hostEmail];
        }

        $meetings = [];
        $nextPageToken = null;

        do {
            $response = Http::withToken($this->accessToken())->get('https://api.zoom.us/v2/users/' . urlencode($hostEmail) . '/meetings', array_filter([
                'type' => 'upcoming',
                'page_size' => 300,
                'next_page_token' => $nextPageToken,
            ]));

            if (!$response->successful()) {
                throw new \RuntimeException($response->json('message', 'Unable to verify Zoom host availability'));
            }

            $meetings = array_merge($meetings, $response->json('meetings', []));
            $nextPageToken = $response->json('next_page_token');
        } while ($nextPageToken);

        $this->hostUpcomingMeetingsCache[$hostEmail] = $meetings;

        return $meetings;
    }

    private function deleteSessions(Builder $query, string $actorType, ?int $actorId = null): void
    {
        $sessions = $query->get();

        foreach ($sessions as $session) {
            try {
                $this->deleteMeeting($session->zoom_meeting_id);
            } catch (\Throwable $throwable) {
                Log::warning('Zoom meeting delete failed', [
                    'meeting_id' => $session->zoom_meeting_id,
                    'session_id' => $session->id,
                    'error' => $throwable->getMessage(),
                ]);
            }

            $this->log('deleted', [
                'course_id' => $session->course_id,
                'batch_id' => $session->batch_id,
                'live_session_id' => $session->id,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'meta' => [
                    'meeting_id' => $session->zoom_meeting_id,
                    'host_email' => $session->zoom_host_email,
                    'scheduled_at' => (string) $session->scheduled_at,
                ],
            ]);

            $session->delete();
        }
    }

    private function liveLecturesForCourse(Course $course, ?array $lectureIds = null)
    {
        $query = $course->liveLectures();

        if ($lectureIds) {
            $query->whereIn('id', $lectureIds);
        }

        return $query->get();
    }

    private function allLecturesForCourse(Course $course, ?array $lectureIds = null)
    {
        $query = $course->lectures();

        if ($lectureIds) {
            $query->whereIn('id', $lectureIds);
        }

        return $query->get();
    }

    private function meetingTimezone(): string
    {
        return config('zoom.meeting.timezone') ?: config('app.timezone', 'UTC');
    }

    private function ensureConfigured(): void
    {
        if (!config('zoom.account_id') || !config('zoom.client_id') || !config('zoom.client_secret')) {
            throw new \RuntimeException('Zoom credentials are not configured.');
        }
    }
}
