<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('consultation_requests', 'confirmation_sent_at')) {
                $table->dateTime('confirmation_sent_at')->nullable()->after('scheduled_at');
                $table->index('confirmation_sent_at', 'idx_consultation_confirmation_sent_at');
            }

            if (!Schema::hasColumn('consultation_requests', 'reminder_24h_sent_at')) {
                $table->dateTime('reminder_24h_sent_at')->nullable()->after('confirmation_sent_at');
                $table->index('reminder_24h_sent_at', 'idx_consultation_reminder_24h_sent_at');
            }

            if (!Schema::hasColumn('consultation_requests', 'reminder_1h_sent_at')) {
                $table->dateTime('reminder_1h_sent_at')->nullable()->after('reminder_24h_sent_at');
                $table->index('reminder_1h_sent_at', 'idx_consultation_reminder_1h_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table) {
            if (Schema::hasColumn('consultation_requests', 'confirmation_sent_at')) {
                $table->dropIndex('idx_consultation_confirmation_sent_at');
                $table->dropColumn('confirmation_sent_at');
            }

            if (Schema::hasColumn('consultation_requests', 'reminder_24h_sent_at')) {
                $table->dropIndex('idx_consultation_reminder_24h_sent_at');
                $table->dropColumn('reminder_24h_sent_at');
            }

            if (Schema::hasColumn('consultation_requests', 'reminder_1h_sent_at')) {
                $table->dropIndex('idx_consultation_reminder_1h_sent_at');
                $table->dropColumn('reminder_1h_sent_at');
            }
        });
    }
};
