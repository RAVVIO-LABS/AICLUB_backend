<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $acts = ['DEPOSIT_COMPLETE', 'DEPOSIT_APPROVE'];

        foreach ($acts as $act) {
            $template = DB::table('notification_templates')->where('act', $act)->first();
            if (!$template) {
                continue;
            }

            $emailBody = (string) ($template->email_body ?? '');
            $shortcodes = json_decode((string) ($template->shortcodes ?? '{}'), true);
            if (!is_array($shortcodes)) {
                $shortcodes = [];
            }

            if (!str_contains($emailBody, '{{course_name}}')) {
                $needle = '<div><b>Transaction Number:';
                $insert = '<div><b>Course Name:</b> {{course_name}}</div>';

                if (str_contains($emailBody, $needle)) {
                    $emailBody = str_replace($needle, $insert . $needle, $emailBody);
                } else {
                    $emailBody .= $insert;
                }
            }

            $shortcodes['course_name'] = 'Purchased course name';

            DB::table('notification_templates')
                ->where('act', $act)
                ->update([
                    'email_body' => $emailBody,
                    'shortcodes' => json_encode($shortcodes),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // no-op rollback to avoid overriding customized templates.
    }
};
