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
        Schema::table('audit_log_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(0)->after('event');
            $table->index(['level', 'created_at'], 'idx_audit_level_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_log_events', function (Blueprint $table) {
            $table->dropIndex('idx_audit_level_time');
            $table->dropColumn('level');
        });
    }
};

