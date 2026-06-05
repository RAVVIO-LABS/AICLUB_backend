<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactAcknowledgementNotification extends Notification
{
    use Queueable;

    public function __construct(private string $name)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your message - AIClub')
            ->greeting('Hi ' . $this->name . ',')
            ->line('Thank you for contacting AIClub. We have received your message.')
            ->line('Our team will review it and get back to you as soon as possible.');
    }
}
