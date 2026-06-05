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
        Schema::table('course_sections', function (Blueprint $table) {
            if (!Schema::hasColumn('course_sections', 'is_released_to_students')) {
                $table->tinyInteger('is_released_to_students')
                    ->default(1)
                    ->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            if (Schema::hasColumn('course_sections', 'is_released_to_students')) {
                $table->dropColumn('is_released_to_students');
            }
        });
    }
};
