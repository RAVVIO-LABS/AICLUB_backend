<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zoom_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('live_session_id')->nullable();
            $table->string('actor_type', 30)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 50);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'batch_id']);
            $table->index(['live_session_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_audit_logs');
    }
};
