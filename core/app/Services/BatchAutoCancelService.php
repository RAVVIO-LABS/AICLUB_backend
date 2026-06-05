<?php

namespace App\Services;

use App\Constants\Status;
use App\Lib\LiveClassScheduler;
use App\Models\AdminNotification;
use App\Models\CourseLiveBatch;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveSession;
use App\Models\CourseLiveTeacherAssignment;
use App\Models\CourseZoomBatch;
use App\Models\CourseZoomMeeting;
use App\Models\CourseZoomMeetingOccurrence;
use App\Models\Instructor;
use App\Models\TeacherUnavailabilitySlot;
use App\Services\Zoom\LmsZoomService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BatchAutoCancelService
{
    public function cancelExpiredEmptyBatches(): array
    {
        $results = [
            'live' => 0,
            'zoom' => 0,
            'teachers_released' => 0,
        ];

        $results = $this->cancelLiveBatches($results);
        $results = $this->cancelZoomBatches($results);

        return $results;
    }

    private function cancelLiveBatches(array $results): array
    {
        $batches = CourseLiveBatch::with(['course', 'teachers.teacher'])
            ->where('status', '!=', 'cancelled')
            ->whereDate('start_date', '<', now()->toDateString())
            ->get();

        foreach ($batches as $batch) {
            $bookingCount = CourseLiveBooking::where('batch_id', $batch->id)
                ->whereHas('purchase', function ($query) {
                    $query->where('payment_status', Status::PAYMENT_SUCCESS);
                })
                ->count();

            if ($bookingCount > 0) {
                continue;
            }

            $teacherIds = $batch->teachers
                ->pluck('teacher_instructor_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            app(LmsZoomService::class)->deleteSessionsForBatch($batch, null, 'system');

            TeacherUnavailabilitySlot::where('source_type', CourseLiveBatch::class)
                ->where('source_id', $batch->id)
                ->delete();

            CourseLiveTeacherAssignment::where('course_id', $batch->course_id)
                ->where('batch_id', $batch->id)
                ->update(['is_active' => 0]);

            $batch->status = 'cancelled';
            $batch->save();

            $this->notifyCancellation($teacherIds, $batch, $batch->course, 'No student enrollments were received for this batch before the start date.');

            $results['live']++;
            $results['teachers_released'] += $teacherIds->count();
        }

        return $results;
    }

    private function cancelZoomBatches(array $results): array
    {
        $batches = CourseZoomBatch::with(['course', 'teacher', 'meetings.occurrences'])
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($batches as $batch) {
            $meetingStarts = $this->zoomBatchMeetingStarts($batch);
            $hasStarted = $meetingStarts->isNotEmpty() && Carbon::parse((string) $meetingStarts->min())->lt(now()->startOfDay());

            if (!$hasStarted) {
                continue;
            }

            $bookingCount = CourseLiveBooking::where('batch_id', $batch->id)
                ->whereHas('purchase', function ($query) {
                    $query->where('payment_status', Status::PAYMENT_SUCCESS);
                })
                ->count();

            if ($bookingCount > 0) {
                continue;
            }

            $teacherIds = collect([$batch->teacher_instructor_id])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $meetingIds = $batch->meetings->pluck('id')->all();
            if (!empty($meetingIds)) {
                CourseZoomMeetingOccurrence::whereIn('course_zoom_meeting_id', $meetingIds)->delete();
                CourseZoomMeeting::whereIn('id', $meetingIds)->delete();
            }

            TeacherUnavailabilitySlot::where('source_type', CourseZoomBatch::class)
                ->where('source_id', $batch->id)
                ->delete();

            CourseLiveTeacherAssignment::where('course_id', $batch->course_id)
                ->where('batch_id', $batch->id)
                ->update(['is_active' => 0]);

            $batch->status = 'cancelled';
            $batch->save();

            $this->notifyCancellation($teacherIds, $batch, $batch->course, 'No student enrollments were received for this batch before the start date.');

            $results['zoom']++;
            $results['teachers_released'] += $teacherIds->count();
        }

        return $results;
    }

    private function notifyCancellation(Collection $teacherIds, object $batch, ?object $course, string $reason): void
    {
        foreach ($teacherIds as $teacherId) {
            $teacher = Instructor::find((int) $teacherId);
            if (!$teacher || empty($teacher->email)) {
                continue;
            }

            try {
                app(TeacherNotificationService::class)->notifyBatchCancelled($teacher, $batch, $course, $reason);
            } catch (\Throwable $throwable) {
                Log::error('Failed to send auto batch cancellation notification', [
                    'teacher_id' => $teacher->id,
                    'batch_id' => $batch->id ?? null,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        try {
            $adminNotification = new AdminNotification();
            $adminNotification->user_id = 0;
            $adminNotification->instructor_id = null;
            $adminNotification->title = 'Batch cancelled automatically: ' . ($batch->title ?? ('Batch #' . ($batch->id ?? '')));
            $adminNotification->click_url = $course && !empty($course->slug) ? urlPath('admin.courses.details', $course->slug) : urlPath('admin.dashboard');
            $adminNotification->save();
        } catch (\Throwable $throwable) {
            Log::error('Failed to create auto batch cancellation admin notification', [
                'batch_id' => $batch->id ?? null,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function zoomBatchMeetingStarts(CourseZoomBatch $batch): Collection
    {
        $batch->loadMissing('meetings.occurrences');

        return $batch->meetings
            ->flatMap(function ($meeting) {
                return $meeting->occurrences->pluck('start_time');
            })
            ->filter();
    }
}
