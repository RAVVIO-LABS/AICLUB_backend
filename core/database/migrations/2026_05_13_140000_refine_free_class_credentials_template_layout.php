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

        $emailBody = '<div>Hey {{student_name}}!</div>'
            . '<div><br></div>'
            . '<div>Your free AIClub class is confirmed.</div>'
            . '<div><br></div>'
            . '<div>🕐 {{class_datetime}} IST</div>'
            . '<div>⏱️ Duration: {{duration_minutes}} minutes</div>'
            . '<div>👩‍🏫 Teacher: {{teacher_name}}</div>'
            . '<div>🔗 Zoom Link: <a href="{{zoom_link}}">{{zoom_link}}</a></div>'
            . '<div><br></div>'
            . '<div><strong>Login Credentials</strong></div>'
            . '<div>Email: <strong>{{login_email}}</strong></div>'
            . '<div>Password: <strong>{{login_password}}</strong></div>'
            . '<div><br></div>'
            . '<div>Please copy credentials exactly as shown (password is case-sensitive).</div>'
            . '<div><br></div>'
            . '<div>Team AIClub India 🇮🇳</div>';

        DB::table('notification_templates')
            ->where('id', $template->id)
            ->update([
                'email_body' => $emailBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};

