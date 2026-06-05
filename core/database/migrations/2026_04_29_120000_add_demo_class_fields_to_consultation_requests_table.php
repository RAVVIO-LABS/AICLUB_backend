<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table) {
            $table->dateTime('preferred_at')->nullable()->after('source');
            $table->unsignedBigInteger('assigned_teacher_id')->nullable()->after('preferred_at');
            $table->unsignedBigInteger('course_id')->nullable()->after('assigned_teacher_id');
            $table->dateTime('scheduled_at')->nullable()->after('course_id');
            $table->unsignedSmallInteger('meeting_duration')->nullable()->after('scheduled_at');
            $table->unsignedBigInteger('scheduled_by_teacher_id')->nullable()->after('meeting_duration');
            $table->string('status', 40)->default('pending_admin_assignment')->after('scheduled_by_teacher_id');
            $table->string('zoom_meeting_id', 255)->nullable()->after('status');
            $table->text('zoom_join_url')->nullable()->after('zoom_meeting_id');
            $table->string('zoom_host_email', 190)->nullable()->after('zoom_join_url');
            $table->text('notes')->nullable()->after('zoom_host_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_at',
                'assigned_teacher_id',
                'course_id',
                'scheduled_at',
                'meeting_duration',
                'scheduled_by_teacher_id',
                'status',
                'zoom_meeting_id',
                'zoom_join_url',
                'zoom_host_email',
                'notes',
            ]);
        });
    }
};