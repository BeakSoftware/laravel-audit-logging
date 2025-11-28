<?php

namespace Lunnar\AuditLogging;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Lunnar\AuditLogging\Console\Commands\ApplyRetentionPolicyCommand;
use Lunnar\AuditLogging\Http\Middleware\EnsureReferenceId;
use Lunnar\AuditLogging\Http\Middleware\LogRequest;

class AuditLoggingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/audit-logging.php', 'audit-logging');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register middleware globally
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(EnsureReferenceId::class);
        $kernel->pushMiddleware(LogRequest::class);

        // Publish config
        $this->publishes([
            __DIR__.'/../config/audit-logging.php' => config_path('audit-logging.php'),
        ], 'audit-logging-config');

        // Publish migrations
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'audit-logging-migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ApplyRetentionPolicyCommand::class,
            ]);

            $this->registerScheduledTasks();
        }
    }

    /**
     * Register scheduled tasks for the retention policies.
     */
    protected function registerScheduledTasks(): void
    {
        $eventSchedule = config('audit-logging.retention.schedule');
        $requestSchedule = config('audit-logging.request_log_retention.schedule');

        if ($eventSchedule === null && $requestSchedule === null) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $scheduler) use ($eventSchedule, $requestSchedule) {
            // Schedule event retention
            if ($eventSchedule !== null) {
                $eventTask = $scheduler->command('audit:retention --events')->at('03:00');

                match ($eventSchedule) {
                    'daily' => $eventTask->daily(),
                    'weekly' => $eventTask->weekly(),
                    'monthly' => $eventTask->monthly(),
                    default => null,
                };
            }

            // Schedule request log retention
            if ($requestSchedule !== null) {
                $requestTask = $scheduler->command('audit:retention --requests')->at('03:15');

                match ($requestSchedule) {
                    'daily' => $requestTask->daily(),
                    'weekly' => $requestTask->weekly(),
                    'monthly' => $requestTask->monthly(),
                    default => null,
                };
            }
        });
    }
}
