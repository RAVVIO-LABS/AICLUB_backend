<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $act = 'FREE_CLASS_RESCHEDULED';
        $exists = DB::table('notification_templates')->where('act', $act)->exists();
        if ($exists) {
            return;
        }

        DB::table('notification_templates')->insert([
            'act' => $act,
            'name' => 'Free Class Rescheduled',
            'subject' => 'Your free class has been rescheduled: {{class_datetime}}',
            'push_title' => null,
            'email_body' => '<div>Hey {{student_name}}!</div><div><br></div><div>Your free class schedule has been updated.</div><div><br></div><div>🕐 New Date & Time: {{class_datetime}} IST</div><div>⏱️ Duration: {{duration_minutes}} minutes</div><div>👩‍🏫 Teacher: {{teacher_name}}</div><div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div><div><br></div><div>Please use the above updated schedule.</div><div><br></div><div>Team AIClub India 🇮🇳</div>',
            'sms_body' => null,
            'shortcodes' => json_encode([
                'student_name' => 'Student/parent name',
                'class_datetime' => 'Updated class date and time',
                'duration_minutes' => 'Updated class duration in minutes',
                'teacher_name' => 'Assigned teacher',
                'zoom_link' => 'Zoom join URL',
            ]),
            'email_status' => 1,
            'email_sent_from_name' => null,
            'email_sent_from_address' => null,
            'sms_status' => 0,
            'sms_sent_from' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('act', 'FREE_CLASS_RESCHEDULED')->delete();
    }
};
