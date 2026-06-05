<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teacher_unavailability_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_instructor_id');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['teacher_instructor_id', 'start_at'], 'idx_teacher_unavail_start');
            $table->index(['teacher_instructor_id', 'end_at'], 'idx_teacher_unavail_end');
            $table->index(['start_at', 'end_at'], 'idx_teacher_unavail_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_unavailability_slots');
    }
};
