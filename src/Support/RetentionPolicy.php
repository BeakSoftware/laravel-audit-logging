<?php

namespace Lunnar\AuditLogging\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lunnar\AuditLogging\Models\AuditLogEvent;
use Lunnar\AuditLogging\Models\AuditLogRequest;

class RetentionPolicy
{
    /**
     * Run the retention policy for audit log events.
     */
    public function runEvents(): int
    {
        return $this->deleteEvents();
    }

    /**
     * Run the retention policy for request logs.
     */
    public function runRequests(): int
    {
        return $this->deleteRequests();
    }

    /**
     * Delete audit log events older than the configured threshold.
     */
    public function deleteEvents(): int
    {
        $days = config('audit-logging.retention.delete_after');

        if ($days === null) {
            return 0;
        }

        $threshold = CarbonImmutable::now()->subDays($days);

        // Delete related subjects first, then audit log events
        $auditLogIds = AuditLogEvent::query()
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

            $deleted += AuditLogEvent::query()
                ->whereIn('id', $chunk)
                ->delete();
        }

        return $deleted;
    }

    /**
     * Delete request logs older than the configured threshold.
     */
    public function deleteRequests(): int
    {
        $days = config('audit-logging.request_log_retention.delete_after');

        if ($days === null) {
            return 0;
        }

        $threshold = CarbonImmutable::now()->subDays($days);

        // Delete in chunks to avoid memory issues
        $deleted = 0;
        $chunkSize = 1000;

        do {
            $count = AuditLogRequest::query()
                ->where('created_at', '<', $threshold)
                ->limit($chunkSize)
                ->delete();

            $deleted += $count;
        } while ($count === $chunkSize);

        return $deleted;
    }
}
