<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_live_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('course_purchased_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('assigned_teacher_id')->nullable();
            $table->string('zoom_host_user_id')->nullable();
            $table->string('zoom_host_email')->nullable();
            $table->string('zoom_subaccount_id')->nullable();
            $table->unsignedInteger('session_number')->default(1);
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('meeting_name');
            $table->unsignedBigInteger('zoom_meeting_id')->nullable();
            $table->text('zoom_join_url')->nullable();
            $table->string('recording_status')->default('cloud');
            $table->string('status', 30)->default('scheduled');
            $table->timestamps();

            $table->index(['course_id', 'batch_id']);
            $table->index(['course_purchased_id', 'user_id']);
            $table->index(['assigned_teacher_id', 'scheduled_at']);
            $table->index(['zoom_host_email', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_live_sessions');
    }
};
