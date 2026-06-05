<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $templates = [
            'FREE_CLASS_REMINDER_24H' => [
                'email_body' => '<div>Hey {{student_name}}!</div><div><br></div><div>Just a quick reminder: your free AIClub class is tomorrow.</div><div><br></div><div>🕐 {{class_datetime}} IST</div><div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div><div><br></div><div>See you soon!</div><div>Team AIClub India 🇮🇳</div>',
                'shortcodes' => [
                    'student_name' => 'Student/parent name',
                    'class_time' => 'Class time',
                    'class_datetime' => 'Class date and time',
                    'zoom_link' => 'Zoom join URL',
                ],
            ],
            'FREE_CLASS_REMINDER_1H' => [
                'email_body' => '<div>Hey {{student_name}}!</div><div><br></div><div>Your free AIClub class starts in 60 minutes.</div><div><br></div><div>🕐 {{class_time}} IST | Today</div><div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div><div><br></div><div>Team AIClub India 🇮🇳</div>',
                'shortcodes' => [
                    'student_name' => 'Student/parent name',
                    'class_time' => 'Class time',
                    'zoom_link' => 'Zoom join URL',
                ],
            ],
        ];

        foreach ($templates as $act => $config) {
            DB::table('notification_templates')
                ->where('act', $act)
                ->update([
                    'email_body' => $config['email_body'],
                    'shortcodes' => json_encode($config['shortcodes']),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No-op rollback: original reminder bodies vary by deployment edits.
    }
};
