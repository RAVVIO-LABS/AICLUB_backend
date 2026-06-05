<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsultationAcknowledgementNotification extends Notification
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
            ->subject('We received your free class request - AIClub')
            ->greeting('Hi ' . $this->name . ',')
            ->line('Thank you for booking a free class with AIClub.')
            ->line('We have received your request and our team will schedule a proper class for you shortly.')
            ->line('We will contact you soon with the next steps.');
    }
}
