<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_zoom_meeting_occurrences', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_instructor_id')->nullable()->after('course_zoom_meeting_id');
            $table->index(['teacher_instructor_id', 'start_time'], 'czm_occ_teacher_start_idx');
        });
    }

    public function down(): void
    {
        Schema::table('course_zoom_meeting_occurrences', function (Blueprint $table) {
            $table->dropIndex('czm_occ_teacher_start_idx');
            $table->dropColumn('teacher_instructor_id');
        });
    }
};
