<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_zoom_meetings', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('instructor_id');
            $table->index(['batch_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::table('course_zoom_meetings', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'start_time']);
            $table->dropColumn('batch_id');
        });
    }
};
