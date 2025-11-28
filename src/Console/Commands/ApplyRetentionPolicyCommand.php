<?php

namespace Lunnar\AuditLogging\Console\Commands;

use Illuminate\Console\Command;
use Lunnar\AuditLogging\Support\RetentionPolicy;

class ApplyRetentionPolicyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:retention
                            {--events : Only process audit log events}
                            {--requests : Only process request logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old audit logs and request logs based on the configured retention policies';

    /**
     * Execute the console command.
     */
    public function handle(RetentionPolicy $policy): int
    {
        $onlyEvents = $this->option('events');
        $onlyRequests = $this->option('requests');
        $processAll = ! $onlyEvents && ! $onlyRequests;

        $hasPolicy = false;

        // Process audit log events
        if ($processAll || $onlyEvents) {
            $eventRetention = config('audit-logging.retention.delete_after');

            if ($eventRetention !== null) {
                $hasPolicy = true;
                $this->info("Deleting audit log events older than {$eventRetention} days...");
                $deletedEvents = $policy->runEvents();
                $this->info("Deleted {$deletedEvents} audit log event(s).");
            } elseif ($onlyEvents) {
                $this->warn('No event retention policy configured. Set retention.delete_after in config/audit-logging.php');
            }
        }

        // Process request logs
        if ($processAll || $onlyRequests) {
            $requestRetention = config('audit-logging.request_log_retention.delete_after');

            if ($requestRetention !== null) {
                $hasPolicy = true;
                $this->info("Deleting request logs older than {$requestRetention} days...");
                $deletedRequests = $policy->runRequests();
                $this->info("Deleted {$deletedRequests} request log(s).");
            } elseif ($onlyRequests) {
                $this->warn('No request log retention policy configured. Set request_log_retention.delete_after in config/audit-logging.php');
            }
        }

        if (! $hasPolicy && $processAll) {
            $this->warn('No retention policies configured. Set delete_after values in config/audit-logging.php');
        }

        return self::SUCCESS;
    }
}
