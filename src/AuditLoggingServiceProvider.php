<?php

namespace Lunnar\AuditLogging;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Lunnar\AuditLogging\Console\Commands\ApplyRetentionPolicyCommand;
use Lunnar\AuditLogging\Http\Middleware\EnsureReferenceId;

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
        // Register the EnsureReferenceId middleware globally
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(EnsureReferenceId::class);

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
     * Register scheduled tasks for the retention policy.
     */
    protected function registerScheduledTasks(): void
    {
        $schedule = config('audit-logging.retention.schedule');

        if ($schedule === null) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $scheduler) use ($schedule) {
            $task = $scheduler->command('audit:retention')->at('03:00');

            match ($schedule) {
                'daily' => $task->daily(),
                'weekly' => $task->weekly(),
                'monthly' => $task->monthly(),
                default => null,
            };
        });
    }
}
