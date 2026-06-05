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
        Schema::table('course_resources', function (Blueprint $table) {
            if (!Schema::hasColumn('course_resources', 'link_title')) {
                $table->string('link_title')->nullable()->after('file');
            }
            if (!Schema::hasColumn('course_resources', 'link_url')) {
                $table->text('link_url')->nullable()->after('link_title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_resources', function (Blueprint $table) {
            if (Schema::hasColumn('course_resources', 'link_url')) {
                $table->dropColumn('link_url');
            }
            if (Schema::hasColumn('course_resources', 'link_title')) {
                $table->dropColumn('link_title');
            }
        });
    }
};
