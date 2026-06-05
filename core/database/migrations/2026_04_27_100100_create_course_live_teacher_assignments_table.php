<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course_live_teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('teacher_instructor_id');
            $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();

            $table->index(['course_id', 'batch_id'], 'idx_live_teacher_course_batch');
            $table->index(['teacher_instructor_id', 'is_active'], 'idx_live_teacher_active');
            $table->unique(['course_id', 'batch_id', 'teacher_instructor_id'], 'uniq_course_batch_teacher');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_live_teacher_assignments');
    }
};
