<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $template = DB::table('notification_templates')
            ->where('act', 'FREE_CLASS_CONFIRMATION')
            ->first();

        if (!$template) {
            return;
        }

        $shortcodes = json_decode((string) ($template->shortcodes ?? '{}'), true);
        if (!is_array($shortcodes)) {
            $shortcodes = [];
        }

        $shortcodes['duration_minutes'] = 'Class duration in minutes';
        $shortcodes['login_email'] = 'Student login email';
        $shortcodes['login_password'] = 'Student login password';

        $emailBody = (string) ($template->email_body ?? '');
        if (!str_contains($emailBody, '{{login_email}}')) {
            $emailBody .= '<div><br></div><div><strong>Login Credentials</strong></div><div>Email: {{login_email}}</div><div>Password: {{login_password}}</div>';
        }
        if (!str_contains($emailBody, '{{duration_minutes}}')) {
            $emailBody = str_replace(
                '🕐 {{class_datetime}} IST</div>',
                '🕐 {{class_datetime}} IST</div><div>⏱️ Duration: {{duration_minutes}} minutes</div>',
                $emailBody
            );
        }

        DB::table('notification_templates')
            ->where('id', $template->id)
            ->update([
                'email_body' => $emailBody,
                'shortcodes' => json_encode($shortcodes),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally no-op because admins may edit templates manually after this migration.
    }
};

