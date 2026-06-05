<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('notification_templates')->where('act', 'CLASS_SESSION_REMINDER')->exists();

        if ($exists) {
            return;
        }

        DB::table('notification_templates')->insert([
            'act' => 'CLASS_SESSION_REMINDER',
            'name' => 'Class Session Reminder',
            'subject' => 'Reminder: {{course_name}} class starts in 1 hour',
            'push_title' => null,
            'email_body' => '<div>Hello {{fullname}},</div><div><br></div><div>This is a reminder that your class is scheduled to begin in 1 hour.</div><div><br></div><div><b>Course:</b> {{course_name}}</div><div><b>Batch:</b> {{batch_name}}</div><div><b>Date & Time:</b> {{class_datetime}}</div><div><b>Teacher:</b> {{teacher_name}}</div><div><b>Zoom Link:</b> <a href="{{zoom_link}}">{{zoom_link}}</a></div><div><br></div><div>Please join on time and keep your materials ready.</div><div><br></div><div>Best regards,</div><div>{{site_name}}</div>',
            'sms_body' => 'Reminder: {{course_name}} starts in 1 hour at {{class_datetime}}. Join: {{zoom_link}}',
            'shortcodes' => json_encode([
                'course_name' => 'Course title',
                'batch_name' => 'Batch title',
                'class_datetime' => 'Scheduled class date and time',
                'teacher_name' => 'Assigned teacher full name',
                'zoom_link' => 'Zoom meeting join URL',
            ]),
            'email_status' => 1,
            'sms_status' => 0,
            'email_sent_from_name' => null,
            'email_sent_from_address' => null,
            'sms_sent_from' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('act', 'CLASS_SESSION_REMINDER')->delete();
    }
};
