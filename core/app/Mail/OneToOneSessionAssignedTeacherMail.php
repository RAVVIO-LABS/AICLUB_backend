<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OneToOneSessionAssignedTeacherMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $teacherName,
        public string $studentName,
        public string $courseName,
        public string $grade,
        public string $dateTime,
    ) {
    }

    public function build()
    {
        return $this->subject('1:1 Session Assigned - ' . $this->studentName)
            ->text('emails.one_to_one_session_assigned_teacher');
    }
}
