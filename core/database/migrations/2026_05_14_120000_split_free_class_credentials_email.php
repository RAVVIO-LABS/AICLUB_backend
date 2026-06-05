<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $confirmation = DB::table('notification_templates')
            ->where('act', 'FREE_CLASS_CONFIRMATION')
            ->first();

        if ($confirmation) {
            $confirmationShortcodes = json_decode((string) ($confirmation->shortcodes ?? '{}'), true);
            if (!is_array($confirmationShortcodes)) {
                $confirmationShortcodes = [];
            }

            $confirmationShortcodes['duration_minutes'] = 'Class duration in minutes';
            unset($confirmationShortcodes['login_email'], $confirmationShortcodes['login_password']);
            unset($confirmationShortcodes['teacher_name']);

            $confirmationBody = '<div>Hey {{student_name}}!</div>'
                . '<div><br></div>'
                . '<div>Your free AIClub class is confirmed.</div>'
                . '<div><br></div>'
                . '<div>🕐 {{class_datetime}} IST</div>'
                . '<div>⏱️ Duration: {{duration_minutes}} minutes</div>'
                . '<div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div>'
                . '<div><br></div>'
                . '<div>Team AIClub India 🇮🇳</div>';

            DB::table('notification_templates')
                ->where('id', $confirmation->id)
                ->update([
                    'email_body' => $confirmationBody,
                    'shortcodes' => json_encode($confirmationShortcodes),
                    'updated_at' => now(),
                ]);
        }

        $credentials = DB::table('notification_templates')
            ->where('act', 'FREE_CLASS_CREDENTIALS')
            ->first();

        $credentialsBody = '<div>Hey {{student_name}}!</div>'
            . '<div><br></div>'
            . '<div>Your AIClub dashboard login credentials are ready.</div>'
            . '<div><br></div>'
            . '<div>Email: <strong>{{login_email}}</strong></div>'
            . '<div>Password: <strong>{{login_password}}</strong></div>'
            . '<div><br></div>'
            . '<div>Please copy credentials exactly as shown (password is case-sensitive).</div>'
            . '<div><br></div>'
            . '<div>Team AIClub India 🇮🇳</div>';

        $credentialsShortcodes = json_encode([
            'student_name' => 'Student/parent name',
            'login_email' => 'Student login email',
            'login_password' => 'Student login password',
        ]);

        if ($credentials) {
            DB::table('notification_templates')
                ->where('id', $credentials->id)
                ->update([
                    'name' => 'Free Class Credentials',
                    'subject' => 'Your AIClub dashboard login credentials',
                    'email_body' => $credentialsBody,
                    'shortcodes' => $credentialsShortcodes,
                    'email_status' => 1,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('notification_templates')->insert([
                'act' => 'FREE_CLASS_CREDENTIALS',
                'name' => 'Free Class Credentials',
                'subject' => 'Your AIClub dashboard login credentials',
                'push_title' => null,
                'email_body' => $credentialsBody,
                'sms_body' => null,
                'shortcodes' => $credentialsShortcodes,
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
        // no-op
    }
};
