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
        Schema::create('audit_log_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('audit_log_id');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('role')->default('primary');

            $table->foreign('audit_log_id')
                ->references('id')
                ->on('audit_logs')
                ->cascadeOnDelete();

            $table->index(['subject_type', 'subject_id', 'audit_log_id'], 'idx_subject_lookup');
            $table->unique(['audit_log_id', 'subject_type', 'subject_id', 'role'], 'uq_subject_per_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log_subjects');
    }
};

