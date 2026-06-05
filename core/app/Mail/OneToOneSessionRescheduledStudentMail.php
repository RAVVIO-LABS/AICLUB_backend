<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OneToOneSessionRescheduledStudentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $studentName,
        public string $courseName,
        public string $grade,
        public string $oldDateTime,
        public string $newDateTime,
        public string $teacherName,
    ) {
    }

    public function build()
    {
        return $this->subject('1:1 Session Rescheduled - Updated Schedule')
            ->text('emails.one_to_one_session_rescheduled_student');
    }
}
