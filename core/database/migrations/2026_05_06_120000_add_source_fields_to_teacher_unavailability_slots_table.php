<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teacher_unavailability_slots', function (Blueprint $table) {
            $table->string('source_type', 100)->nullable()->after('reason');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id'], 'idx_teacher_unavail_source');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_unavailability_slots', function (Blueprint $table) {
            $table->dropIndex('idx_teacher_unavail_source');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
