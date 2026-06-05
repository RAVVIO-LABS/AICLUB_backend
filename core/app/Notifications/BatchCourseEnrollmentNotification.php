<?php

namespace App\Notifications;

use App\Models\CourseLiveBatch;
use App\Models\CoursePurchased;
use App\Models\CourseZoomBatch;
use App\Models\Deposit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatchCourseEnrollmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private CoursePurchased $coursePurchased,
        private Deposit $deposit,
        private ?CourseLiveBatch $liveBatch = null,
        private ?CourseZoomBatch $zoomBatch = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentName = (string) ($this->coursePurchased->user?->fullname ?: 'N/A');
        $grade = (string) ($this->coursePurchased->course?->category?->name ?: 'N/A');
        $courseName = (string) ($this->coursePurchased->course?->title ?: 'N/A');
        $email = (string) ($this->coursePurchased->user?->email ?: 'N/A');
        $phoneNumber = (string) ($this->coursePurchased->user?->mobileNumber ?: 'N/A');
        $purchaseDate = showDateTime($this->deposit->created_at, 'd M Y');

        return (new MailMessage)
            ->subject($studentName . ': New Batch Course Enrollment')
            ->greeting('Hello,')
            ->line($studentName . ' has enrolled in a batch course through the website.')
            ->line('Enrollment Details:')
            ->line('Student Name: ' . $studentName)
            ->line('Grade: ' . $grade)
            ->line('Batch Name: ' . $this->resolveBatchName())
            ->line('Course Name: ' . $courseName)
            ->line('Email ID: ' . $email)
            ->line('Phone Number: ' . $phoneNumber)
            ->line('Purchase Date: ' . $purchaseDate)
            ->line('Batch Start Date: ' . $this->resolveBatchStartDate())
            ->line('Batch End Date: ' . $this->resolveBatchEndDate())
            ->line('Assigned Teacher: ' . $this->resolveTeacherName())
            ->line('Session Time: ' . $this->resolveSessionTime())
            ->line('Thank you.');
    }

    private function resolveBatchName(): string
    {
        if (!empty($this->liveBatch?->title)) {
            return (string) $this->liveBatch->title;
        }

        if (!empty($this->zoomBatch?->title)) {
            return (string) $this->zoomBatch->title;
        }

        return 'N/A';
    }

    private function resolveBatchStartDate(): string
    {
        if (!empty($this->liveBatch?->start_date)) {
            return showDateTime($this->liveBatch->start_date, 'd M Y');
        }

        if ($this->zoomBatch) {
            $firstStart = $this->zoomBatchDateTimes()->sort()->first();
            if ($firstStart) {
                return $firstStart->format('d M Y');
            }
        }

        if (!empty($this->coursePurchased->liveBooking?->start_date)) {
            return showDateTime($this->coursePurchased->liveBooking->start_date, 'd M Y');
        }

        return 'N/A';
    }

    private function resolveBatchEndDate(): string
    {
        if (!empty($this->liveBatch?->end_date)) {
            return showDateTime($this->liveBatch->end_date, 'd M Y');
        }

        if ($this->zoomBatch) {
            $lastStart = $this->zoomBatchDateTimes()->sort()->last();
            if ($lastStart) {
                return $lastStart->format('d M Y');
            }
        }

        return 'N/A';
    }

    private function resolveTeacherName(): string
    {
        $teacher = $this->liveBatch?->teachers?->first()?->teacher ?: $this->zoomBatch?->teacher;
        if (!$teacher) {
            return 'N/A';
        }

        return trim((string) (($teacher->firstname ?? '') . ' ' . ($teacher->lastname ?? ''))) ?: 'N/A';
    }

    private function resolveSessionTime(): string
    {
        if (!empty($this->liveBatch?->meeting_start_time) && !empty($this->liveBatch?->meeting_end_time)) {
            return showDateTime($this->liveBatch->meeting_start_time, 'h:i A') . ' - ' . showDateTime($this->liveBatch->meeting_end_time, 'h:i A');
        }

        if (!empty($this->liveBatch?->meeting_start_time)) {
            return showDateTime($this->liveBatch->meeting_start_time, 'h:i A');
        }

        if ($this->zoomBatch) {
            $firstStart = $this->zoomBatchDateTimes()->sort()->first();
            if ($firstStart) {
                $duration = $this->resolveZoomDurationMinutes();
                if ($duration > 0) {
                    return $firstStart->format('h:i A') . ' - ' . $firstStart->copy()->addMinutes($duration)->format('h:i A');
                }

                return $firstStart->format('h:i A');
            }
        }

        return 'N/A';
    }

    private function zoomBatchDateTimes()
    {
        return collect($this->zoomBatch?->meetings ?? [])
            ->flatMap(function ($meeting) {
                $occurrences = collect($meeting->occurrences ?? [])
                    ->filter(fn ($occurrence) => !empty($occurrence->start_time))
                    ->map(fn ($occurrence) => Carbon::parse($occurrence->start_time));

                if ($occurrences->isNotEmpty()) {
                    return $occurrences;
                }

                if (!empty($meeting->start_time)) {
                    return [Carbon::parse($meeting->start_time)];
                }

                return [];
            });
    }

    private function resolveZoomDurationMinutes(): int
    {
        foreach (collect($this->zoomBatch?->meetings ?? []) as $meeting) {
            $occurrence = collect($meeting->occurrences ?? [])->first(fn ($item) => !empty($item->duration_minutes));
            if ($occurrence && !empty($occurrence->duration_minutes)) {
                return (int) $occurrence->duration_minutes;
            }

            if (!empty($meeting->duration_minutes)) {
                return (int) $meeting->duration_minutes;
            }
        }

        return 0;
    }
}
