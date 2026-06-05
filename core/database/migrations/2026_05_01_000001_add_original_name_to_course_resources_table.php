<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_resources', function (Blueprint $table) {
            if (!Schema::hasColumn('course_resources', 'original_name')) {
                $table->string('original_name')->nullable()->after('file');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_resources', function (Blueprint $table) {
            if (Schema::hasColumn('course_resources', 'original_name')) {
                $table->dropColumn('original_name');
            }
        });
    }
};

