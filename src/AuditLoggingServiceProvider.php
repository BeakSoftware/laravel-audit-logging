<?php

namespace Lunnar\AuditLogging;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
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
    }
}
