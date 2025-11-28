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
        Schema::create('audit_log_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('method', 10);
            $table->text('url');
            $table->string('route_name')->nullable();
            $table->string('route_action')->nullable();
            $table->integer('status_code')->nullable();
            $table->float('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->uuid('reference_id')->index();
            $table->json('request_headers')->nullable();
            $table->json('request_query')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['created_at']);
            $table->index(['actor_id', 'created_at'], 'idx_request_actor_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log_requests');
    }
};
