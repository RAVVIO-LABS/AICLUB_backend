<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_zoom_batches', function (Blueprint $table) {
            $table->unique(['course_id', 'instructor_id', 'title'], 'uniq_course_zoom_batch_title_per_instructor');
        });
    }

    public function down(): void
    {
        Schema::table('course_zoom_batches', function (Blueprint $table) {
            $table->dropUnique('uniq_course_zoom_batch_title_per_instructor');
        });
    }
};
