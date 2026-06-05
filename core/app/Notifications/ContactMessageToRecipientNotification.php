<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageToRecipientNotification extends Notification
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
        return (new MailMessage)
            ->subject('New Contact Form Submission - AIClub')
            ->greeting('Hello,')
            ->line('A new message has been submitted from the AIClub contact form.')
            ->line('Name: ' . ($this->payload['name'] ?? ''))
            ->line('Email: ' . ($this->payload['email'] ?? ''))
            ->line('Subject: ' . ($this->payload['subject'] ?? ''))
            ->line('Message:')
            ->line((string) ($this->payload['message'] ?? ''))
            ->line('Please review and follow up with the user.');
    }
}
