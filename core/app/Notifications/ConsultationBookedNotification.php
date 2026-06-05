<?php

namespace App\Notifications;

use App\Models\ConsultationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsultationBookedNotification extends Notification
{
    use Queueable;

    public function __construct(private ConsultationRequest $consultationRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentName = (string) $this->consultationRequest->parent_student_name;
        $grade = (string) $this->consultationRequest->child_grade;
        $date = showDateTime($this->consultationRequest->preferred_at, 'd M Y');
        $time = showDateTime($this->consultationRequest->preferred_at, 'h:i A');

        return (new MailMessage)
            ->subject('New Free Class Booking: ' . $studentName)
            ->greeting('Hello,')
            ->line($studentName . ' (Grade ' . $grade . ') has booked a free class.')
            ->line('Details:')
            ->line('Student Name: ' . $studentName)
            ->line('Grade: ' . $grade)
            ->line('Email ID: ' . $this->consultationRequest->email_address)
            ->line('Phone Number: ' . $this->consultationRequest->phone_number)
            ->line('Date: ' . $date)
            ->line('Time: ' . $time)
            ->line('Please proceed with the necessary coordination and follow-up.')
            ->line('Thank you.');
    }
}
