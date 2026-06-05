<?php

namespace App\Notifications;

use App\Models\CoursePurchased;
use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OneToOneCourseEnrollmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private CoursePurchased $coursePurchased,
        private Deposit $deposit
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentName = (string) ($this->coursePurchased->user?->fullname ?: 'N/A');
        $category = (string) ($this->coursePurchased->course?->category?->name ?: 'N/A');
        $courseName = (string) ($this->coursePurchased->course?->title ?: 'N/A');
        $email = (string) ($this->coursePurchased->user?->email ?: 'N/A');
        $phoneNumber = (string) ($this->coursePurchased->user?->mobileNumber ?: 'N/A');
        $purchaseDate = showDateTime($this->deposit->created_at, 'd M Y');
        $amountPaid = showAmount($this->deposit->amount, currencyFormat: false);

        return (new MailMessage)
            ->subject($studentName . ': New 1:1 Course Enrollment')
            ->greeting('Hello,')
            ->line($studentName . ' has enrolled in a 1:1 course through the website.')
            ->line('Enrollment Details:')
            ->line('Student Name: ' . $studentName)
            ->line('Category: ' . $category)
            ->line('Course Name: ' . $courseName)
            ->line('Email ID: ' . $email)
            ->line('Phone Number: ' . $phoneNumber)
            ->line('Purchase Date: ' . $purchaseDate)
            ->line('Amount Paid: ' . $amountPaid)
            ->line('Action Required:')
            ->line('Please assign a suitable teacher and coordinate with the family to schedule a class time that works for both the student and the instructor.')
            ->line('Thank you.');
    }
}
