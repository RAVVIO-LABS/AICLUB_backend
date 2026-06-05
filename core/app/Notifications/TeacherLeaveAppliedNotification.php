<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeacherLeaveAppliedNotification extends Notification
{
    use Queueable;

    public function __construct(private array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $teacherName = (string) ($this->payload['teacher_name'] ?? '');
        $teacherUsername = (string) ($this->payload['teacher_username'] ?? '');
        $dateRange = (string) ($this->payload['date_range'] ?? '');
        $reason = (string) ($this->payload['reason'] ?? '');

        return (new MailMessage)
            ->subject('Teacher Leave Request - AIClub')
            ->greeting('Hello,')
            ->line('A teacher has submitted a leave request.')
            ->line('Teacher: ' . $teacherName . ($teacherUsername ? (' (@' . $teacherUsername . ')') : ''))
            ->line('Date Range: ' . $dateRange)
            ->line('Reason: ' . $reason)
            ->line('Please review the request in the admin panel.');
    }
}
