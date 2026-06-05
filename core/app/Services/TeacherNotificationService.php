<?php

namespace App\Services;

use App\Models\ConsultationRequest;
use App\Models\Course;
use App\Models\CourseLiveBatch;
use App\Models\CourseZoomBatch;
use App\Models\Instructor;
use App\Models\NotificationTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TeacherNotificationService
{
    public function notifyFreeClassAssigned(ConsultationRequest $request, Instructor $teacher, bool $isReassigned = false): void
    {
        $template = $isReassigned ? 'TEACHER_FREE_CLASS_REASSIGNED' : 'TEACHER_FREE_CLASS_ASSIGNED';

        if (!$this->canNotifyTeacher($teacher, $template)) {
            return;
        }

        notify($teacher, $template, $this->freeClassShortcodes($request, $teacher), ['email']);
    }

    public function notifyFreeClassCancelledUnavailable(ConsultationRequest $request, Instructor $teacher): void
    {
        $template = 'TEACHER_FREE_CLASS_CANCELLED_UNAVAILABLE';

        if (!$this->canNotifyTeacher($teacher, $template)) {
            return;
        }

        notify($teacher, $template, $this->freeClassShortcodes($request, $teacher), ['email']);
    }

    public function notifyBatchCreated(Instructor $teacher, object $batch, ?Course $course = null, array $overrides = []): void
    {
        $template = 'TEACHER_BATCH_CREATED';

        if (!$this->canNotifyTeacher($teacher, $template)) {
            return;
        }

        notify($teacher, $template, $this->batchShortcodes($teacher, $batch, $course, $overrides), ['email']);
    }

    public function notifyBatchAssigned(Instructor $teacher, object $batch, ?Course $course = null, array $overrides = []): void
    {
        $template = 'TEACHER_BATCH_ASSIGNED';

        if (!$this->canNotifyTeacher($teacher, $template)) {
            return;
        }

        notify($teacher, $template, $this->batchShortcodes($teacher, $batch, $course, $overrides), ['email']);
    }

    public function notifyBatchCancelled(Instructor $teacher, object $batch, ?Course $course = null, string $reason = ''): void
    {
        $template = 'TEACHER_BATCH_CANCELLED';

        if (!$this->canNotifyTeacher($teacher, $template)) {
            return;
        }

        notify($teacher, $template, $this->batchShortcodes($teacher, $batch, $course, [
            'reason' => $reason ?: 'No student enrollments were received for this batch before the start date.',
        ]), ['email']);
    }

    private function canNotifyTeacher(?Instructor $teacher, string $templateCode): bool
    {
        if (!$teacher || empty($teacher->id) || empty($teacher->email)) {
            return false;
        }

        return NotificationTemplate::where('act', $templateCode)
            ->where('email_status', 1)
            ->exists();
    }

    private function freeClassShortcodes(ConsultationRequest $request, Instructor $teacher): array
    {
        return [
            'teacher_name' => $this->teacherName($teacher),
            'student_name' => (string) ($request->parent_student_name ?: 'Student'),
            'grade' => (string) ($request->child_grade ?: 'N/A'),
            'student_email' => (string) ($request->email_address ?: ''),
            'student_phone' => (string) ($request->phone_number ?: ''),
            'class_date' => $request->scheduled_at ? Carbon::parse($request->scheduled_at)->format('d M Y') : 'N/A',
            'class_time' => $request->scheduled_at ? Carbon::parse($request->scheduled_at)->format('h:i A') . ' IST' : 'N/A',
        ];
    }

    private function batchShortcodes(Instructor $teacher, object $batch, ?Course $course = null, array $overrides = []): array
    {
        $resolvedCourse = $course ?: $this->resolveCourse($batch);

        return [
            'teacher_name' => $this->teacherName($teacher),
            'batch_name' => (string) (($overrides['batch_name'] ?? null) ?: ($batch->title ?? ('Batch #' . ($batch->id ?? '')))),
            'course_name' => (string) (($overrides['course_name'] ?? null) ?: ($resolvedCourse?->title ?? 'N/A')),
            'grade_range' => (string) (($overrides['grade_range'] ?? null) ?: $this->resolveGradeGroup($batch, $resolvedCourse)),
            'start_date' => (string) (($overrides['start_date'] ?? null) ?: $this->resolveBatchStartDate($batch)),
            'end_date' => (string) (($overrides['end_date'] ?? null) ?: $this->resolveBatchEndDate($batch)),
            'session_schedule' => (string) (($overrides['session_schedule'] ?? null) ?: $this->formatSessionSchedule($batch)),
            'reason' => (string) (($overrides['reason'] ?? null) ?: 'No student enrollments were received for this batch before the start date.'),
        ];
    }

    private function resolveCourse(object $batch): ?Course
    {
        if ($batch instanceof CourseLiveBatch || $batch instanceof CourseZoomBatch) {
            $batch->loadMissing('course.category');
            return $batch->course;
        }

        return null;
    }

    private function resolveGradeGroup(object $batch, ?Course $course = null): string
    {
        $course = $course ?: $this->resolveCourse($batch);

        return (string) ($course?->category?->name ?: 'N/A');
    }

    private function resolveBatchStartDate(object $batch): string
    {
        if ($batch instanceof CourseLiveBatch) {
            return $this->formatDate($batch->start_date ?? null);
        }

        if ($batch instanceof CourseZoomBatch) {
            $startAt = $this->zoomBatchOccurrences($batch)->min('start_time');
            if (!$startAt) {
                $startAt = $this->zoomBatchMeetings($batch)->min('start_time');
            }

            return $this->formatDateTimeValue($startAt);
        }

        return 'N/A';
    }

    private function resolveBatchEndDate(object $batch): string
    {
        if ($batch instanceof CourseLiveBatch) {
            return $this->formatDate($batch->end_date ?? null);
        }

        if ($batch instanceof CourseZoomBatch) {
            $endAt = $this->zoomBatchOccurrences($batch)->max('start_time');
            if (!$endAt) {
                $endAt = $this->zoomBatchMeetings($batch)->max('start_time');
            }

            return $this->formatDateTimeValue($endAt);
        }

        return 'N/A';
    }

    private function teacherName(Instructor $teacher): string
    {
        return trim((string) ($teacher->fullname ?? trim(($teacher->firstname ?? '') . ' ' . ($teacher->lastname ?? ''))))
            ?: (string) ($teacher->username ?? 'Teacher');
    }

    private function formatDate(?string $date): string
    {
        if (!$date) {
            return 'N/A';
        }

        return Carbon::parse($date)->format('d M Y');
    }

    private function formatDateTimeValue($value): string
    {
        if (!$value) {
            return 'N/A';
        }

        return Carbon::parse((string) $value)->format('d M Y');
    }

    private function formatSessionSchedule(object $batch): string
    {
        if (!empty($batch->meeting_start_time) && !empty($batch->meeting_end_time)) {
            return Carbon::parse((string) $batch->meeting_start_time)->format('h:i A')
                . ' - '
                . Carbon::parse((string) $batch->meeting_end_time)->format('h:i A');
        }

        if ($batch instanceof CourseLiveBatch && $batch->relationLoaded('sessions')) {
            $firstSession = $batch->sessions->sortBy('scheduled_at')->first();
            if ($firstSession && !empty($firstSession->scheduled_at) && !empty($firstSession->duration_minutes)) {
                $startAt = Carbon::parse($firstSession->scheduled_at);
                $endAt = $startAt->copy()->addMinutes((int) $firstSession->duration_minutes);

                return $startAt->format('h:i A') . ' - ' . $endAt->format('h:i A');
            }
        }

        if ($batch instanceof CourseZoomBatch) {
            $occurrenceCount = $this->zoomBatchOccurrences($batch)->count();
            if ($occurrenceCount > 0) {
                return $occurrenceCount . ' ' . str('meeting')->plural($occurrenceCount);
            }

            $meetingCount = $this->zoomBatchMeetings($batch)->count();
            if ($meetingCount > 0) {
                return $meetingCount . ' ' . str('meeting')->plural($meetingCount);
            }
        }

        return 'N/A';
    }

    private function zoomBatchMeetings(CourseZoomBatch $batch): Collection
    {
        $batch->loadMissing('meetings');

        return $batch->meetings instanceof Collection ? $batch->meetings : collect();
    }

    private function zoomBatchOccurrences(CourseZoomBatch $batch): Collection
    {
        $batch->loadMissing('meetings.occurrences');

        return $this->zoomBatchMeetings($batch)
            ->flatMap(fn ($meeting) => $meeting->occurrences ?? collect())
            ->filter();
    }
}
