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
        Schema::create('course_live_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('created_by_instructor_id')->nullable();
            $table->string('class_type', 20)->default('group');
            $table->string('title');
            $table->string('zoom_meeting_link')->nullable();
            $table->unsignedInteger('batch_number')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('meeting_start_time')->nullable();
            $table->time('meeting_end_time')->nullable();
            $table->unsignedInteger('class_duration')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index(['course_id', 'class_type']);
            $table->index(['course_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_live_batches');
    }
};
