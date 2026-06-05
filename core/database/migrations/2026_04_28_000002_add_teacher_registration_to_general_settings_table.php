<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('general_settings') && !Schema::hasColumn('general_settings', 'teacher_registration')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->boolean('teacher_registration')->default(1)->after('instructor_registration');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'teacher_registration')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('teacher_registration');
            });
        }
    }
};
