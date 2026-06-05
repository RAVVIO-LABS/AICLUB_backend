<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_live_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('course_live_sessions', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('scheduled_at');
                $table->index('reminder_sent_at', 'idx_course_live_sessions_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_live_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('course_live_sessions', 'reminder_sent_at')) {
                $table->dropIndex('idx_course_live_sessions_reminder_sent_at');
                $table->dropColumn('reminder_sent_at');
            }
        });
    }
};
