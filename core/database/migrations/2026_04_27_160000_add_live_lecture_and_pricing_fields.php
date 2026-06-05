<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'group_price')) {
                $table->decimal('group_price', 28, 8)->nullable()->after('price');
            }

            if (!Schema::hasColumn('courses', 'one_to_one_price')) {
                $table->decimal('one_to_one_price', 28, 8)->nullable()->after('group_price');
            }
        });

        Schema::table('course_lectures', function (Blueprint $table) {
            if (!Schema::hasColumn('course_lectures', 'is_live_class')) {
                $table->tinyInteger('is_live_class')->default(0)->after('is_preview');
            }
        });

        Schema::table('course_live_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('course_live_batches', 'lecture_ids')) {
                $table->json('lecture_ids')->nullable()->after('capacity');
            }
        });

        Schema::table('course_live_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('course_live_sessions', 'course_lecture_id')) {
                $table->unsignedBigInteger('course_lecture_id')->nullable()->after('course_id');
                $table->index(['course_lecture_id'], 'idx_live_session_lecture');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_live_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('course_live_sessions', 'course_lecture_id')) {
                $table->dropIndex('idx_live_session_lecture');
                $table->dropColumn('course_lecture_id');
            }
        });

        Schema::table('course_live_batches', function (Blueprint $table) {
            if (Schema::hasColumn('course_live_batches', 'lecture_ids')) {
                $table->dropColumn('lecture_ids');
            }
        });

        Schema::table('course_lectures', function (Blueprint $table) {
            if (Schema::hasColumn('course_lectures', 'is_live_class')) {
                $table->dropColumn('is_live_class');
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'one_to_one_price')) {
                $table->dropColumn('one_to_one_price');
            }

            if (Schema::hasColumn('courses', 'group_price')) {
                $table->dropColumn('group_price');
            }
        });
    }
};
