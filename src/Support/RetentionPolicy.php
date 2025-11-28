<?php

namespace Lunnar\AuditLogging\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lunnar\AuditLogging\Models\AuditLog;

class RetentionPolicy
{
    /**
     * Run the retention policy (delete old audit logs).
     */
    public function run(): int
    {
        return $this->delete();
    }

    /**
     * Delete audit logs older than the configured threshold.
     */
    public function delete(): int
    {
        $days = config('audit-logging.retention.delete_after');

        if ($days === null) {
            return 0;
        }

        $threshold = CarbonImmutable::now()->subDays($days);

        // Delete related subjects first, then audit logs
        $auditLogIds = AuditLog::query()
            ->where('created_at', '<', $threshold)
            ->pluck('id');

        if ($auditLogIds->isEmpty()) {
            return 0;
        }

        // Delete in chunks to avoid memory issues
        $deleted = 0;

        foreach ($auditLogIds->chunk(1000) as $chunk) {
            DB::table('audit_log_subjects')
                ->whereIn('audit_log_id', $chunk)
                ->delete();

            $deleted += AuditLog::query()
                ->whereIn('id', $chunk)
                ->delete();
        }

        return $deleted;
    }
}
