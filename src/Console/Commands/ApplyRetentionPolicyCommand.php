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
    protected $signature = 'audit:retention';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old audit logs based on the configured retention policy';

    /**
     * Execute the console command.
     */
    public function handle(RetentionPolicy $policy): int
    {
        $deleteAfter = config('audit-logging.retention.delete_after');

        if ($deleteAfter === null) {
            $this->warn('No retention policy configured. Set delete_after in config/audit-logging.php');

            return self::SUCCESS;
        }

        $this->info("Deleting audit logs older than {$deleteAfter} days...");

        $deleted = $policy->run();

        $this->info("Deleted {$deleted} audit log(s).");

        return self::SUCCESS;
    }
}
