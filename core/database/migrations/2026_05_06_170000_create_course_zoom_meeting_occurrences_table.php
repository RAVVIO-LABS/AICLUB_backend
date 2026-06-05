<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_zoom_meeting_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_zoom_meeting_id');
            $table->string('title', 255)->nullable();
            $table->dateTime('start_time');
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->text('zoom_join_url')->nullable();
            $table->string('zoom_occurrence_id', 100)->nullable();
            $table->string('status', 50)->default('scheduled');
            $table->timestamps();

            $table->index(['course_zoom_meeting_id', 'start_time'], 'czm_occ_meeting_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_zoom_meeting_occurrences');
    }
};
