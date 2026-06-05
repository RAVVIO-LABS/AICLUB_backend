<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `course_zoom_batches` MODIFY `teacher_instructor_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `course_zoom_batches` MODIFY `teacher_instructor_id` BIGINT UNSIGNED NOT NULL');
    }
};

