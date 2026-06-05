<?php

namespace App\Notifications;

use App\Models\ConsultationRequest;
use App\Models\Course;
use App\Models\Instructor;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsultationTeacherAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ConsultationRequest $consultationRequest,
        private Instructor $teacher,
        private Course $course,
        private bool $isReschedule = false
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentName = trim((string) ($this->consultationRequest->parent_student_name ?: 'Student'));
        $scheduledAt = $this->consultationRequest->scheduled_at
            ? Carbon::parse($this->consultationRequest->scheduled_at)->format('d M Y h:i A')
            : 'N/A';
        $duration = (int) ($this->consultationRequest->meeting_duration ?: 0);
        $actionText = $this->isReschedule ? 'rescheduled' : 'assigned';

        $mail = (new MailMessage())
            ->subject('Consultation Session ' . ucfirst($actionText) . ' - ' . ($this->course->title ?: 'AIClub'))
            ->greeting('Hi ' . trim((string) ($this->teacher->firstname . ' ' . $this->teacher->lastname)) . '!')
            ->line('A consultation session has been ' . $actionText . ' to you.')
            ->line('Student: ' . $studentName)
            ->line('Student Grade: ' . (string) ($this->consultationRequest->child_grade ?: 'N/A'))
            ->line('Student Email: ' . (string) ($this->consultationRequest->email_address ?: 'N/A'))
            ->line('Student Phone: ' . (string) ($this->consultationRequest->phone_number ?: 'N/A'))
            ->line('Course: ' . (string) ($this->course->title ?: 'N/A'))
            ->line('Scheduled Date & Time: ' . $scheduledAt . ' IST')
            ->line('Duration: ' . ($duration > 0 ? $duration . ' minutes' : 'N/A'));

        if (!empty($this->consultationRequest->zoom_join_url)) {
            $mail->line('Zoom Link: ' . $this->consultationRequest->zoom_join_url);
        }

        if (!empty($this->consultationRequest->notes)) {
            $mail->line('Notes: ' . $this->consultationRequest->notes);
        }

        return $mail->line('Please be ready a few minutes before the scheduled time.');
    }
}

