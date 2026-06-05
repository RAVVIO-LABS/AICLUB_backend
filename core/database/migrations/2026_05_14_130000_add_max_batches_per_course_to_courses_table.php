<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'max_batches_per_course')) {
                $table->unsignedSmallInteger('max_batches_per_course')->default(10)->after('batch_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'max_batches_per_course')) {
                $table->dropColumn('max_batches_per_course');
            }
        });
    }
};
