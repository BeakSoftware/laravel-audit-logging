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
        Schema::create('audit_log_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event')->index();
            $table->json('message_data')->nullable();
            $table->json('payload')->nullable();
            $table->json('diff')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->uuid('reference_id')->nullable()->index();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->string('checksum', 128)->nullable();

            $table->index(['event', 'created_at'], 'idx_audit_event_time');
            $table->index(['actor_id', 'created_at'], 'idx_audit_actor_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log_events');
    }
};
