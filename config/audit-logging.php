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
    | Collect Request Metadata
    |--------------------------------------------------------------------------
    |
    | Whether to collect HTTP request metadata (IP, user agent, route, etc.)
    | with each audit log entry.
    |
    */
    'collect_request_metadata' => true,

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    |
    | Configure how long audit logs are retained before being deleted.
    |
    | delete_after: Days until audit logs are deleted. Set to null to disable.
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
];

