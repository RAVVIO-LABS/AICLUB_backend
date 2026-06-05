<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_zoom_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('instructor_id');
            $table->string('topic', 255);
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->unsignedSmallInteger('duration_minutes')->default(40);
            $table->string('timezone', 100)->default('UTC');
            $table->json('recurrence')->nullable();
            $table->string('zoom_meeting_id', 190)->nullable();
            $table->text('zoom_join_url')->nullable();
            $table->string('zoom_host_email', 190)->nullable();
            $table->string('zoom_host_user_id', 190)->nullable();
            $table->string('status', 50)->default('scheduled');
            $table->timestamps();

            $table->index(['course_id', 'start_time']);
            $table->index(['instructor_id', 'start_time']);
            $table->index(['zoom_host_email', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_zoom_meetings');
    }
};
