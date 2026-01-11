<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Key
    |--------------------------------------------------------------------------
    |
    | This key is used to compute HMAC checksums for audit log entries,
    | ensuring data integrity. Set this to a secure random string in your
    | .env file as AUDIT_KEY.
    |
    */
    'audit_key' => env('AUDIT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Exclude Fields
    |--------------------------------------------------------------------------
    |
    | These fields will be excluded from audit log payloads by default.
    | Override per-model using the $auditExclude static property.
    |
    */
    'default_exclude' => ['id', 'created_at', 'updated_at', 'deleted_at'],

    /*
    |--------------------------------------------------------------------------
    | Default Ignore Changes
    |--------------------------------------------------------------------------
    |
    | These fields will be ignored when detecting changes on update events.
    | Override per-model using the $auditIgnoreChanges static property.
    |
    */
    'default_ignore_changes' => ['updated_at'],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Field Patterns
    |--------------------------------------------------------------------------
    |
    | Fields containing these strings (case-insensitive) will be redacted
    | in audit logs. Add custom patterns using SensitiveDataSanitizer::addSensitiveFields().
    |
    */
    'sensitive_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'api_secret',
        'secret',
        'secret_key',
        'private_key',
        'public_key',
        'auth_token',
        'bearer_token',
        'authorization',
        'credit_card',
        'card_number',
        'full_number',
        'cvv',
        'cvc',
        'ssn',
        'social_security',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actor Model
    |--------------------------------------------------------------------------
    |
    | The model class to use for the actor relationship. This should be your
    | User model or equivalent.
    |
    */
    'actor_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Event Levels
    |--------------------------------------------------------------------------
    |
    | Event levels control the visibility of audit log entries. Use levels to
    | show different events to different user types. Define your own level
    | scheme based on your application's needs.
    |
    | When querying, use forLevel() scope to filter events at or below a level.
    | Lower levels are more visible, higher levels are more restricted.
    |
    | Example scheme:
    |   0   = Events visible to end-users
    |   50  = Events visible to organization owners
    |   100 = Events visible to developers/admins
    |
    | default_level: The level assigned to events when not explicitly specified.
    |
    */
    'default_level' => 0,

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Configure HTTP request logging behavior.
    |
    | enabled:            Enable or disable request logging entirely.
    |                     Set to false to disable all request logging.
    |
    | only_authenticated: When true, only log requests from authenticated users.
    |                     This helps filter out bot traffic and unauthenticated
    |                     requests. Set to false to log all requests.
    |
    | exclude_methods:    HTTP methods to exclude from logging. Useful for
    |                     excluding read-only requests like GET.
    |                     Example: ['GET', 'HEAD', 'OPTIONS']
    |
    */
    'request_logging' => [
        'enabled' => true,
        'only_authenticated' => false,
        'exclude_methods' => ['GET', 'HEAD', 'OPTIONS'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Retention Policy
    |--------------------------------------------------------------------------
    |
    | Configure how long audit log events are retained before being deleted.
    |
    | delete_after: Days until audit log events are deleted. Set to null to disable.
    |
    | schedule:     Automatically schedule the retention command.
    |               Options: 'daily', 'weekly', 'monthly', or null to disable.
    |               When enabled, the command runs at 3:00 AM.
    |
    */
    'retention' => [
        'delete_after' => null, // e.g., 365 (days)
        'schedule' => null,     // e.g., 'daily'
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Log Retention Policy
    |--------------------------------------------------------------------------
    |
    | Configure how long request logs are retained before being deleted.
    | This is separate from event retention, allowing different retention
    | periods for requests vs audit events.
    |
    | delete_after: Days until request logs are deleted. Set to null to disable.
    |
    | schedule:     Automatically schedule the retention command.
    |               Options: 'daily', 'weekly', 'monthly', or null to disable.
    |               When enabled, the command runs at 3:00 AM.
    |
    */
    'request_log_retention' => [
        'delete_after' => null, // e.g., 30 (days)
        'schedule' => null,     // e.g., 'daily'
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing Request Logging
    |--------------------------------------------------------------------------
    |
    | Configure outgoing HTTP request logging behavior. This logs all requests
    | made using Laravel's HTTP client (Http facade / Illuminate\Http\Client).
    |
    | enabled:      Enable or disable outgoing request logging entirely.
    |               Set to false to disable all outgoing request logging.
    |
    | exclude_urls: URL patterns to exclude from logging. Supports wildcards.
    |               Example: ['https://api.example.com/*', '*health-check*']
    |
    */
    'outgoing_request_logging' => [
        'enabled' => true,
        'exclude_urls' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing Request Log Retention Policy
    |--------------------------------------------------------------------------
    |
    | Configure how long outgoing request logs are retained before being deleted.
    | This is separate from other retention policies, allowing different retention
    | periods for outgoing requests.
    |
    | delete_after: Days until outgoing request logs are deleted. Set to null to disable.
    |
    | schedule:     Automatically schedule the retention command.
    |               Options: 'daily', 'weekly', 'monthly', or null to disable.
    |               When enabled, the command runs at 3:30 AM.
    |
    */
    'outgoing_request_log_retention' => [
        'delete_after' => null, // e.g., 30 (days)
        'schedule' => null,     // e.g., 'daily'
    ],
];

