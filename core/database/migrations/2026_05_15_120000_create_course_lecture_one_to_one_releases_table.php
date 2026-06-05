<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_lecture_one_to_one_releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('course_section_id');
            $table->unsignedBigInteger('course_lecture_id');
            $table->unsignedBigInteger('live_booking_id');
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('is_released')->default(1)->index();
            $table->string('released_by_type', 20)->nullable();
            $table->unsignedBigInteger('released_by_id')->nullable();
            $table->timestamps();

            $table->unique(['course_lecture_id', 'live_booking_id'], 'uniq_lecture_one_to_one_release');
            $table->index(['course_id', 'live_booking_id'], 'idx_one_to_one_release_course_booking');
            $table->index(['user_id', 'live_booking_id'], 'idx_one_to_one_release_user_booking');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lecture_one_to_one_releases');
    }
};
