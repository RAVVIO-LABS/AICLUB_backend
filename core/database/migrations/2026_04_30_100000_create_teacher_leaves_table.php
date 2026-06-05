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
        Schema::create('teacher_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_instructor_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['teacher_instructor_id', 'status'], 'idx_teacher_leave_status');
            $table->index(['from_date', 'to_date'], 'idx_teacher_leave_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_leaves');
    }
};
