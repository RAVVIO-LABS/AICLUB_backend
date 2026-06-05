<?php

namespace App\Lib;

use App\Models\ConsultationRequest;
use App\Models\CourseLiveBatch;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveSession;
use App\Models\CourseLiveTeacherAssignment;
use App\Models\CourseZoomMeetingOccurrence;
use App\Models\Instructor;
use App\Models\TeacherLeave;
use App\Models\TeacherUnavailabilitySlot;
use Carbon\Carbon;

class LiveClassScheduler
{
    protected static function parseDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse(trim($date . ' ' . $time));
    }

    protected static function hasLiveSessionClash(int $teacherId, Carbon $windowStart, Carbon $windowEnd, ?int $excludeBatchId = null, ?int $excludeSessionId = null): bool
    {
        $assignments = CourseLiveTeacherAssignment::where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->get();

        $allocatedBatchIds = $assignments->pluck('batch_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $allocatedCourseIds = $assignments->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $byTeacher = CourseLiveSession::whereIn('status', ['scheduled', 'assigned', 'started'])
            ->where('assigned_teacher_id', $teacherId)
            ->get();

        $byBatch = collect();
        if ($allocatedBatchIds->isNotEmpty()) {
            $byBatch = CourseLiveSession::whereIn('status', ['scheduled', 'assigned', 'started'])
                ->whereIn('batch_id', $allocatedBatchIds->all())
                ->get();
        }

        $byCourse = collect();
        if ($allocatedCourseIds->isNotEmpty()) {
            $byCourse = CourseLiveSession::whereIn('status', ['scheduled', 'assigned', 'started'])
                ->whereIn('course_id', $allocatedCourseIds->all())
                ->get();
        }

        $sessions = $byTeacher
            ->merge($byBatch)
            ->merge($byCourse)
            ->unique('id')
            ->values();

        foreach ($sessions as $session) {
            if (!$session->scheduled_at) {
                continue;
            }

            if ($excludeBatchId && (int) $session->batch_id === $excludeBatchId) {
                continue;
            }

            if ($excludeSessionId && (int) $session->id === $excludeSessionId) {
                continue;
            }

            $sessionStart = Carbon::parse($session->scheduled_at);
            $sessionEnd = $sessionStart->copy()->addMinutes((int) ($session->duration_minutes ?? 0));

            if ($sessionStart->lt($windowEnd) && $sessionEnd->gt($windowStart)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasOneToOneBookingClash(int $teacherId, Carbon $windowStart, Carbon $windowEnd, ?int $excludeBookingId = null): bool
    {
        $date = $windowStart->toDateString();

        $query = CourseLiveBooking::where('assigned_teacher_id', $teacherId)
            ->where('class_type', 'one_to_one')
            ->where('start_date', $date)
            ->whereIn('status', ['pending_admin_assignment', 'assigned', 'confirmed']);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        $bookings = $query->get();
        foreach ($bookings as $booking) {
            if (!$booking->start_time || !$booking->end_time) {
                continue;
            }

            $bookingStart = self::parseDateTime((string) $booking->start_date, (string) $booking->start_time);
            $bookingEnd = self::parseDateTime((string) $booking->start_date, (string) $booking->end_time);

            if ($bookingStart->lt($windowEnd) && $bookingEnd->gt($windowStart)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasDemoClassClash(int $teacherId, Carbon $windowStart, Carbon $windowEnd): bool
    {
        $demoMeetings = ConsultationRequest::where('assigned_teacher_id', $teacherId)
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->get();

        foreach ($demoMeetings as $meeting) {
            $demoStart = Carbon::parse($meeting->scheduled_at);
            $demoEnd = $demoStart->copy()->addMinutes((int) ($meeting->meeting_duration ?: 30));

            if ($demoStart->lt($windowEnd) && $demoEnd->gt($windowStart)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasCourseZoomOccurrenceClash(int $teacherId, Carbon $windowStart, Carbon $windowEnd, ?int $excludeMeetingId = null): bool
    {
        $occurrences = CourseZoomMeetingOccurrence::where('teacher_instructor_id', $teacherId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('start_time')
            ->when($excludeMeetingId, function ($query) use ($excludeMeetingId) {
                $query->where('course_zoom_meeting_id', '!=', $excludeMeetingId);
            })
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereBetween('start_time', [$windowStart->copy()->subDay(), $windowEnd->copy()->addDay()]);
            })
            ->get();

        foreach ($occurrences as $occurrence) {
            $occurrenceStart = Carbon::parse($occurrence->start_time);
            $occurrenceEnd = $occurrenceStart->copy()->addMinutes((int) ($occurrence->duration_minutes ?? 0));

            if ($occurrenceStart->lt($windowEnd) && $occurrenceEnd->gt($windowStart)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasManualUnavailableClash(int $teacherId, Carbon $windowStart, Carbon $windowEnd): bool
    {
        $slots = TeacherUnavailabilitySlot::where('teacher_instructor_id', $teacherId)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereBetween('start_at', [$windowStart, $windowEnd])
                    ->orWhereBetween('end_at', [$windowStart, $windowEnd])
                    ->orWhere(function ($inner) use ($windowStart, $windowEnd) {
                        $inner->where('start_at', '<=', $windowStart)
                            ->where('end_at', '>=', $windowEnd);
                    });
            })
            ->get();

        return $slots->isNotEmpty();
    }

    public static function hasTeacherConflict(int $teacherId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null, ?int $excludeBatchId = null, ?int $excludeMeetingId = null, ?int $excludeSessionId = null): bool
    {
        if (self::hasApprovedLeaveConflict($teacherId, $date)) {
            return true;
        }

        $windowStart = self::parseDateTime($date, $startTime);
        $windowEnd = self::parseDateTime($date, $endTime);

        if (self::hasLiveSessionClash($teacherId, $windowStart, $windowEnd, $excludeBatchId, $excludeSessionId)) {
            return true;
        }

        if (self::hasOneToOneBookingClash($teacherId, $windowStart, $windowEnd, $excludeBookingId)) {
            return true;
        }

        if (self::hasDemoClassClash($teacherId, $windowStart, $windowEnd)) {
            return true;
        }

        if (self::hasCourseZoomOccurrenceClash($teacherId, $windowStart, $windowEnd, $excludeMeetingId)) {
            return true;
        }

        if (self::hasManualUnavailableClash($teacherId, $windowStart, $windowEnd)) {
            return true;
        }

        return false;
    }

    public static function hasApprovedLeaveConflict(int $teacherId, string $startDate, ?string $endDate = null): bool
    {
        $endDate = $endDate ?: $startDate;

        return TeacherLeave::where('teacher_instructor_id', $teacherId)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('from_date', [$startDate, $endDate])
                    ->orWhereBetween('to_date', [$startDate, $endDate])
                    ->orWhere(function ($inner) use ($startDate, $endDate) {
                        $inner->where('from_date', '<=', $startDate)
                            ->where('to_date', '>=', $endDate);
                    });
            })
            ->exists();
    }

    public static function hasUpcomingMeetingWithinLeaveRange(int $teacherId, Carbon $blockStart, Carbon $blockEnd, string $leaveStartDate, string $leaveEndDate): bool
    {
        $assignments = CourseLiveTeacherAssignment::where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->get();

        $allocatedBatchIds = $assignments->pluck('batch_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $allocatedCourseIds = $assignments->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $liveSessionStatuses = ['scheduled', 'assigned', 'started'];

        $byTeacher = CourseLiveSession::whereIn('status', $liveSessionStatuses)
            ->where('assigned_teacher_id', $teacherId)
            ->whereBetween('scheduled_at', [$blockStart, $blockEnd])
            ->whereBetween('scheduled_at', [
                Carbon::parse($leaveStartDate)->startOfDay(),
                Carbon::parse($leaveEndDate)->endOfDay(),
            ])
            ->exists();

        if ($byTeacher) {
            return true;
        }

        if ($allocatedBatchIds->isNotEmpty()) {
            $byBatch = CourseLiveSession::whereIn('status', $liveSessionStatuses)
                ->whereIn('batch_id', $allocatedBatchIds->all())
                ->whereBetween('scheduled_at', [$blockStart, $blockEnd])
                ->whereBetween('scheduled_at', [
                    Carbon::parse($leaveStartDate)->startOfDay(),
                    Carbon::parse($leaveEndDate)->endOfDay(),
                ])
                ->exists();

            if ($byBatch) {
                return true;
            }
        }

        if ($allocatedCourseIds->isNotEmpty()) {
            $byCourse = CourseLiveSession::whereIn('status', $liveSessionStatuses)
                ->whereIn('course_id', $allocatedCourseIds->all())
                ->whereBetween('scheduled_at', [$blockStart, $blockEnd])
                ->whereBetween('scheduled_at', [
                    Carbon::parse($leaveStartDate)->startOfDay(),
                    Carbon::parse($leaveEndDate)->endOfDay(),
                ])
                ->exists();

            if ($byCourse) {
                return true;
            }
        }

        $bookingStatuses = ['pending_admin_assignment', 'assigned', 'confirmed'];
        $bookingStartDate = $blockStart->toDateString();
        $bookingEndDate = $blockEnd->toDateString();
        $bookingRangeStart = max($leaveStartDate, $bookingStartDate);
        $bookingRangeEnd = min($leaveEndDate, $bookingEndDate);

        if ($bookingRangeStart <= $bookingRangeEnd) {
            $bookings = CourseLiveBooking::where('assigned_teacher_id', $teacherId)
                ->where('class_type', 'one_to_one')
                ->whereIn('status', $bookingStatuses)
                ->whereBetween('start_date', [$bookingRangeStart, $bookingRangeEnd])
                ->get();

            foreach ($bookings as $booking) {
                if (!$booking->start_date || !$booking->start_time) {
                    continue;
                }

                $bookingStart = self::parseDateTime((string) $booking->start_date, (string) $booking->start_time);
                if ($bookingStart->between($blockStart, $blockEnd)) {
                    return true;
                }
            }
        }

        $demoMeeting = ConsultationRequest::where('assigned_teacher_id', $teacherId)
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$blockStart, $blockEnd])
            ->whereBetween('scheduled_at', [
                Carbon::parse($leaveStartDate)->startOfDay(),
                Carbon::parse($leaveEndDate)->endOfDay(),
            ])
            ->exists();

        if ($demoMeeting) {
            return true;
        }

        return CourseZoomMeetingOccurrence::where('teacher_instructor_id', $teacherId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('start_time')
            ->whereBetween('start_time', [$blockStart, $blockEnd])
            ->whereBetween('start_time', [
                Carbon::parse($leaveStartDate)->startOfDay(),
                Carbon::parse($leaveEndDate)->endOfDay(),
            ])
            ->exists();
    }

    public static function timeOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }

    public static function dateRangeOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        if (!$startA || !$endA || !$startB || !$endB) {
            return true;
        }

        return $startA <= $endB && $startB <= $endA;
    }

    public static function hasBatchTeacherClash(int $teacherId, int $batchId): bool
    {
        $targetBatch = CourseLiveBatch::find($batchId);
        if (!$targetBatch || !$targetBatch->meeting_start_time || !$targetBatch->meeting_end_time) {
            return false;
        }

        $targetStartDate = (string) $targetBatch->start_date;
        $targetEndDate = (string) ($targetBatch->end_date ?: $targetBatch->start_date);
        if ($targetStartDate && self::hasApprovedLeaveConflict($teacherId, $targetStartDate, $targetEndDate)) {
            return true;
        }

        $targetSessions = $targetBatch->sessions()->whereNotNull('scheduled_at')->get();
        foreach ($targetSessions as $session) {
            $sessionStart = Carbon::parse($session->scheduled_at);
            $sessionEnd = $sessionStart->copy()->addMinutes((int) ($session->duration_minutes ?? 0));
            if (self::hasTeacherConflict(
                $teacherId,
                $sessionStart->toDateString(),
                $sessionStart->format('H:i'),
                $sessionEnd->format('H:i'),
                null,
                $targetBatch->id
            )) {
                return true;
            }
        }

        $assignedBatches = CourseLiveBatch::whereHas('teachers', function ($query) use ($teacherId) {
            $query->where('teacher_instructor_id', $teacherId)->where('is_active', 1);
        })->where('id', '!=', $targetBatch->id)->get();

        foreach ($assignedBatches as $batch) {
            if (!$batch->meeting_start_time || !$batch->meeting_end_time) {
                continue;
            }

            if (!self::dateRangeOverlap($targetBatch->start_date, $targetBatch->end_date, $batch->start_date, $batch->end_date)) {
                continue;
            }

            if (self::timeOverlap($targetBatch->meeting_start_time, $targetBatch->meeting_end_time, $batch->meeting_start_time, $batch->meeting_end_time)) {
                return true;
            }
        }

        return false;
    }

    public static function hasOneToOneClash(int $teacherId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): bool
    {
        return self::hasTeacherConflict($teacherId, $date, $startTime, $endTime, $excludeBookingId, null);
    }

    public static function availableCourseTeachers(int $courseId, ?string $date = null, ?string $startTime = null, ?string $endTime = null, ?int $excludeMeetingId = null): array
    {
        $teachers = Instructor::onlyTeachers()
            ->active()
            ->select('id', 'firstname', 'lastname', 'email', 'username', 'image')
            ->orderBy('firstname')
            ->get();

        if (!$date || !$startTime || !$endTime) {
            return $teachers->values()->all();
        }

        return $teachers
            ->filter(function ($teacher) use ($date, $startTime, $endTime, $excludeMeetingId) {
                return !self::hasTeacherConflict((int) $teacher->id, $date, $startTime, $endTime, null, null, $excludeMeetingId);
            })
            ->values()
            ->all();
    }
}
