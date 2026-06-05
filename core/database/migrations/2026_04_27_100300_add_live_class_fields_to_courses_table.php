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
        if (!Schema::hasColumn('courses', 'live_class_mode') || !Schema::hasColumn('courses', 'default_class_duration')) {
            Schema::table('courses', function (Blueprint $table) {
                if (!Schema::hasColumn('courses', 'live_class_mode')) {
                    $table->string('live_class_mode', 20)->default('group')->after('meeting_duration');
                }

                if (!Schema::hasColumn('courses', 'default_class_duration')) {
                    $table->unsignedInteger('default_class_duration')->nullable()->after('live_class_mode');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'default_class_duration')) {
                $table->dropColumn('default_class_duration');
            }

            if (Schema::hasColumn('courses', 'live_class_mode')) {
                $table->dropColumn('live_class_mode');
            }
        });
    }
};
