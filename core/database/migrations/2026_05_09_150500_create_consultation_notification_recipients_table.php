<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('consultation_notification_recipients')) {
            Schema::create('consultation_notification_recipients', function (Blueprint $table) {
                $table->id();
                $table->string('email', 191)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_notification_recipients');
    }
};
