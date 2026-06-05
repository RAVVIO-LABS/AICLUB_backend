<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_zoom_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('teacher_instructor_id');
            $table->string('title', 255);
            $table->string('status', 50)->default('active');
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index(['teacher_instructor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_zoom_batches');
    }
};
