<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OneToOneSessionRescheduledTeacherMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $teacherName,
        public string $studentName,
        public string $courseName,
        public string $grade,
        public string $oldDateTime,
        public string $newDateTime,
    ) {
    }

    public function build()
    {
        return $this->subject('1:1 Session Rescheduled - ' . $this->studentName)
            ->text('emails.one_to_one_session_rescheduled_teacher');
    }
}
