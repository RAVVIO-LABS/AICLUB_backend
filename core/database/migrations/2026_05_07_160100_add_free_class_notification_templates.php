<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $templates = [
            [
                'act' => 'FREE_CLASS_CONFIRMATION',
                'name' => 'Free Class Confirmation',
                'subject' => 'Free Session Confirmed: {{class_datetime}} | Join Link Inside',
                'email_body' => '<div>Hey {{student_name}}!</div><div><br></div><div>Your free AIClub class is confirmed.</div><div><br></div><div>🕐 {{class_datetime}} IST</div><div>👩‍🏫 Teacher: {{teacher_name}}</div><div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div><div><br></div><div>Team AIClub India 🇮🇳</div>',
                'shortcodes' => [
                    'student_name' => 'Student/parent name',
                    'class_datetime' => 'Class date and time',
                    'teacher_name' => 'Assigned teacher',
                    'zoom_link' => 'Zoom join URL',
                ],
            ],
            [
                'act' => 'FREE_CLASS_REMINDER_24H',
                'name' => 'Free Class 24h Reminder',
                'subject' => 'Reminder: Your free class is tomorrow at {{class_time}}',
                'email_body' => '<div>Hey {{student_name}}!</div><div><br></div><div>Just a quick reminder: your free AIClub class is tomorrow.</div><div><br></div><div>🕐 {{class_datetime}} IST</div><div>👩‍🏫 Teacher: {{teacher_name}}</div><div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div><div><br></div><div>See you soon!</div><div>Team AIClub India 🇮🇳</div>',
                'shortcodes' => [
                    'student_name' => 'Student/parent name',
                    'class_time' => 'Class time',
                    'class_datetime' => 'Class date and time',
                    'teacher_name' => 'Assigned teacher',
                    'zoom_link' => 'Zoom join URL',
                ],
            ],
            [
                'act' => 'FREE_CLASS_REMINDER_1H',
                'name' => 'Free Class 1h Reminder',
                'subject' => 'Your free class starts in 60 minutes',
                'email_body' => '<div>Hey {{student_name}}!</div><div><br></div><div>Your free AIClub class starts in 60 minutes.</div><div><br></div><div>🕐 {{class_time}} IST | Today</div><div>👩‍🏫 Teacher: {{teacher_name}}</div><div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div><div><br></div><div>Team AIClub India 🇮🇳</div>',
                'shortcodes' => [
                    'student_name' => 'Student/parent name',
                    'class_time' => 'Class time',
                    'teacher_name' => 'Assigned teacher',
                    'zoom_link' => 'Zoom join URL',
                ],
            ],
        ];

        foreach ($templates as $item) {
            $exists = DB::table('notification_templates')->where('act', $item['act'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('notification_templates')->insert([
                'act' => $item['act'],
                'name' => $item['name'],
                'subject' => $item['subject'],
                'push_title' => null,
                'email_body' => $item['email_body'],
                'sms_body' => null,
                'shortcodes' => json_encode($item['shortcodes']),
                'email_status' => 1,
                'email_sent_from_name' => null,
                'email_sent_from_address' => null,
                'sms_status' => 0,
                'sms_sent_from' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('act', [
            'FREE_CLASS_CONFIRMATION',
            'FREE_CLASS_REMINDER_24H',
            'FREE_CLASS_REMINDER_1H',
        ])->delete();
    }
};
