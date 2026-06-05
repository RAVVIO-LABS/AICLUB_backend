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
        Schema::table('courses', function (Blueprint $table) {
            $table->string('zoom_meeting_link')->nullable()->after('congrats_message');
            $table->unsignedInteger('batch_number')->nullable()->after('zoom_meeting_link');
            $table->time('meeting_start_time')->nullable()->after('batch_number');
            $table->time('meeting_end_time')->nullable()->after('meeting_start_time');
            $table->unsignedInteger('meeting_duration')->nullable()->after('meeting_end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'zoom_meeting_link',
                'batch_number',
                'meeting_start_time',
                'meeting_end_time',
                'meeting_duration',
            ]);
        });
    }
};
