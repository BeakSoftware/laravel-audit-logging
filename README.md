# Laravel Audit Logging

Automatic audit logging for Laravel Eloquent models via a simple trait.

## Features

- 🔄 Automatic logging of `created`, `updated`, and `deleted` events
- 🎯 Per-model configuration via static properties
- 🔗 Auto-detection of `BelongsTo` relationships as parent subjects
- 🔒 Automatic sanitization of sensitive data (passwords, tokens, etc.)
- 🔐 HMAC checksum for data integrity verification
- 📡 HTTP request logging with full request/response capture
- 📤 Outgoing HTTP request logging (Laravel HTTP client)
- 🔍 Request tracing via `reference_id` linking requests to audit events
- 🗑️ Separate configurable retention policies for events, requests, and outgoing requests

## Installation

```bash
composer require lunnar/laravel-audit-logging
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=audit-logging-config
php artisan vendor:publish --tag=audit-logging-migrations
php artisan migrate
```

Add the `AUDIT_KEY` to your `.env` file:

```env
AUDIT_KEY=your-secure-random-string-here
```

Generate a secure key:

```bash
php artisan tinker --execute="echo bin2hex(random_bytes(32));"
```

## Usage

Add the `HasAuditLogging` trait to any model you want to audit:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Lunnar\AuditLogging\Concerns\HasAuditLogging;

class Product extends Model
{
    use HasAuditLogging;

    /**
     * Fields to exclude from audit payload.
     */
    protected static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Fields to include in audit messageData (for human-readable logs).
     */
    protected static array $auditMessageFields = ['name', 'price'];
}
```

That's it! Now all create, update, and delete operations on `Product` will be automatically logged.

## Model Configuration

Per-model configuration is done via static properties:

| Property                   | Type     | Default                                            | Description                                                        |
| -------------------------- | -------- | -------------------------------------------------- | ------------------------------------------------------------------ |
| `$auditExclude`            | `array`  | `['id', 'created_at', 'updated_at', 'deleted_at']` | Fields to exclude from payload                                     |
| `$auditMessageFields`      | `array`  | `[]`                                               | Fields for messageData (supports `['field' => 'accessor']` syntax) |
| `$auditIgnoreChanges`      | `array`  | `['updated_at']`                                   | Fields to ignore when detecting changes                            |
| `$auditEvents`             | `array`  | `['created', 'updated', 'deleted']`                | Which events to log                                                |
| `$auditEventPrefix`        | `string` | _from morph map/table_                             | Event prefix (e.g., `product`)                                     |
| `$auditSubjectType`        | `string` | _from morph map/table_                             | Subject type for audit entries                                     |
| `$auditAdditionalSubjects` | `array`  | `[]`                                               | Additional related subjects (manual)                               |
| `$auditAutoParentSubjects` | `bool`   | `true`                                             | Auto-detect `BelongsTo` relationships as parent subjects           |
| `$auditExcludeParents`     | `array`  | `[]`                                               | `BelongsTo` relationships to exclude from auto-detection           |

### Example with All Options

```php
class User extends Model
{
    use HasAuditLogging;

    // Exclude sensitive fields from the payload
    protected static array $auditExclude = [
        'id',
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Use accessor for masked email in message data
    protected static array $auditMessageFields = [
        'name',
        'email' => 'email_masked'  // Uses $user->email_masked accessor
    ];

    // Don't log changes to these fields
    protected static array $auditIgnoreChanges = ['updated_at', 'last_login_at'];

    // Only log create and delete events
    protected static array $auditEvents = ['created', 'deleted'];
}
```

### Parent Subjects (BelongsTo Relationships)

By default, the trait **automatically detects** all `BelongsTo` relationships and includes them as parent subjects in audit entries. This requires your relationship methods to have a `BelongsTo` return type hint.

```php
class Product extends Model
{
    use HasAuditLogging;

    // These relationships are automatically detected and included as parent subjects
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

When a `Product` is created/updated/deleted, the audit log will automatically include:
- Primary subject: `products` (the product itself)
- Parent subject: `organizations` (from `organization()`)
- Parent subject: `categories` (from `category()`)

#### Excluding Specific Relationships

To exclude certain relationships from auto-detection:

```php
class Product extends Model
{
    use HasAuditLogging;

    // Don't include these relationships in audit logs
    protected static array $auditExcludeParents = ['createdBy', 'country'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo  // Excluded
    {
        return $this->belongsTo(User::class);
    }
}
```

#### Disabling Auto-Detection

To disable automatic parent detection entirely:

```php
class Product extends Model
{
    use HasAuditLogging;

    protected static bool $auditAutoParentSubjects = false;
}
```

#### Manual Additional Subjects

You can also manually specify additional subjects (useful when auto-detection isn't possible or for non-BelongsTo relationships):

```php
class Role extends Model
{
    use HasAuditLogging;

    protected static array $auditMessageFields = ['name'];

    // Manually specify additional subjects
    protected static array $auditAdditionalSubjects = [
        [
            'type' => 'organizations',           // Subject type
            'foreign_key' => 'organization_id',  // Foreign key on this model
            'role' => 'parent'                   // Role in the audit entry
        ],
    ];
}
```

## Temporarily Disable Logging

```php
Product::withoutAuditLogging(function () {
    Product::create([...]); // No audit log
    Product::find(1)->update([...]); // No audit log
});
```

## Manual Audit Entries

You can also write audit entries manually:

```php
use Lunnar\AuditLogging\Support\Audit;

Audit::write(
    event: 'user.password_reset',
    subjects: [
        ['subject_type' => 'users', 'subject_id' => $user->id, 'role' => 'primary'],
    ],
    messageData: ['email' => $user->email],
    payload: ['reset_method' => 'email'],
);
```

## Querying Audit Log Events

```php
use Lunnar\AuditLogging\Models\AuditLogEvent;

// Get all events for a specific model
$events = AuditLogEvent::forSubject($product)->latest('created_at')->get();

// Get all events by a specific actor
$events = AuditLogEvent::forActor($userId)->get();

// Get all events for a specific event type
$events = AuditLogEvent::forEvent('product.created')->get();

// Get all events matching an event pattern
$events = AuditLogEvent::forEventLike('product.%')->get();

// Get all events for a specific request (via reference_id)
$events = AuditLogEvent::forReferenceId($referenceId)->get();

// Get the HTTP request associated with an event
$event = AuditLogEvent::first();
$request = $event->request(); // Returns AuditLogRequest or null
```

## Request Logging

HTTP requests are automatically logged for all routes in the `web` and `api` middleware groups. The middleware runs after authentication, so it knows whether a user is logged in.

### Configuration

In `config/audit-logging.php`:

```php
'request_logging' => [
    'only_authenticated' => true, // Only log requests from authenticated users
],
```

When `only_authenticated` is `true`, requests from unauthenticated users are completely skipped (no database operations). This helps filter out bot traffic and reduces database load.

### Custom Route Groups

For custom middleware groups, use the `audit.requests` middleware alias:

```php
Route::middleware(['custom-auth', 'audit.requests'])->group(function () {
    // routes
});
```

## Querying Request Logs

HTTP requests are logged to the `audit_log_requests` table.

```php
use Lunnar\AuditLogging\Models\AuditLogRequest;

// Get all requests for a specific reference ID
$requests = AuditLogRequest::forReferenceId($referenceId)->get();

// Get all requests by a specific actor
$requests = AuditLogRequest::forActor($userId)->get();

// Get all requests for a specific route
$requests = AuditLogRequest::forRoute('api.products.store')->get();

// Get all requests with a specific HTTP method
$requests = AuditLogRequest::forMethod('POST')->get();

// Get all failed requests (4xx and 5xx)
$requests = AuditLogRequest::failed()->get();

// Get all successful requests (2xx)
$requests = AuditLogRequest::successful()->get();

// Get the audit events associated with a request
$request = AuditLogRequest::first();
$events = $request->events(); // Returns Collection of AuditLogEvent
```

## Outgoing Request Logging

All outgoing HTTP requests made via Laravel's HTTP client (`Http` facade) are automatically logged. This is useful for tracking API calls to external services.

### Configuration

In `config/audit-logging.php`:

```php
'outgoing_request_logging' => [
    'enabled' => true,
    'exclude_urls' => [
        'https://api.example.com/health*',  // Exclude health checks
        '*localhost*',                       // Exclude local requests
    ],
],
```

The `exclude_urls` option supports wildcard patterns using `*`.

### Querying Outgoing Request Logs

```php
use Lunnar\AuditLogging\Models\AuditLogOutgoingRequest;

// Get all outgoing requests for a specific reference ID
$requests = AuditLogOutgoingRequest::forReferenceId($referenceId)->get();

// Get all outgoing requests matching a URL pattern
$requests = AuditLogOutgoingRequest::forUrl('api.stripe.com')->get();

// Get all outgoing requests with a specific HTTP method
$requests = AuditLogOutgoingRequest::forMethod('POST')->get();

// Get all failed outgoing requests (4xx, 5xx, or connection errors)
$requests = AuditLogOutgoingRequest::failed()->get();

// Get all successful outgoing requests (2xx)
$requests = AuditLogOutgoingRequest::successful()->get();

// Get the audit events associated with an outgoing request (via reference_id)
$request = AuditLogOutgoingRequest::first();
$events = $request->events(); // Returns Collection of AuditLogEvent
```

### Linking Outgoing Requests to Incoming Requests

Outgoing requests are automatically linked to the incoming HTTP request via `reference_id`. This allows you to trace which external API calls were made during a specific user request:

```php
$referenceId = '550e8400-e29b-41d4-a716-446655440000';

// Get the incoming request
$incomingRequest = AuditLogRequest::forReferenceId($referenceId)->first();

// Get all outgoing requests made during that request
$outgoingRequests = AuditLogOutgoingRequest::forReferenceId($referenceId)->get();

// Get all audit events
$events = AuditLogEvent::forReferenceId($referenceId)->get();
```

## Request Tracing

Every HTTP request is assigned a unique `reference_id` (via the `X-Lunnar-Reference-Id` header). This ID links:

- The incoming HTTP request in `audit_log_requests`
- All outgoing HTTP requests in `audit_log_outgoing_requests`
- All audit events triggered during that request in `audit_log_events`

This enables full traceability from a single request to all database changes and external API calls it caused.

```php
// Find all activity during a specific request
$referenceId = '550e8400-e29b-41d4-a716-446655440000';

$incomingRequest = AuditLogRequest::forReferenceId($referenceId)->first();
$outgoingRequests = AuditLogOutgoingRequest::forReferenceId($referenceId)->get();
$events = AuditLogEvent::forReferenceId($referenceId)->get();

// Or from an event, get the original request
$event = AuditLogEvent::first();
$httpRequest = $event->request();
```

## Verifying Checksum Integrity

```php
use Lunnar\AuditLogging\Support\AuditChecksum;
use Lunnar\AuditLogging\Models\AuditLogEvent;

$event = AuditLogEvent::find($id);

$isValid = AuditChecksum::verify([
    'event' => $event->event,
    'message_data' => $event->message_data,
    'payload' => $event->payload,
    'diff' => $event->diff,
    'actor_id' => $event->actor_id,
    'subjects' => $event->subjects->map->only(['subject_type', 'subject_id', 'role'])->all(),
], $event->checksum);
```

## Retention Policy

The package includes separate retention policies for audit log events, request logs, and outgoing request logs, allowing different retention periods for each.

### Configuration

In `config/audit-logging.php`:

```php
// Audit log events retention
'retention' => [
    'delete_after' => 365,   // Delete events after 1 year
    'schedule' => 'daily',   // Automatically run daily at 3:00 AM
],

// Request logs retention (can be shorter since request data is often less critical)
'request_log_retention' => [
    'delete_after' => 30,    // Delete request logs after 30 days
    'schedule' => 'daily',   // Automatically run daily at 3:15 AM
],

// Outgoing request logs retention
'outgoing_request_log_retention' => [
    'delete_after' => 30,    // Delete outgoing request logs after 30 days
    'schedule' => 'daily',   // Automatically run daily at 3:30 AM
],
```

Options for each:
- `delete_after`: Days until records are deleted. Set to `null` to disable.
- `schedule`: `'daily'`, `'weekly'`, `'monthly'`, or `null` to disable automatic scheduling.

### Running Manually

```bash
# Run all retention policies
php artisan audit:retention

# Only process audit log events
php artisan audit:retention --events

# Only process request logs
php artisan audit:retention --requests

# Only process outgoing request logs
php artisan audit:retention --outgoing-requests
```

## Config File Reference

Publish the config file to customize defaults:

```bash
php artisan vendor:publish --tag=audit-logging-config
```

### All Options

| Option | Type | Default | Description |
| ------ | ---- | ------- | ----------- |
| `audit_key` | `string` | `env('AUDIT_KEY')` | HMAC key for checksum integrity verification |
| `default_exclude` | `array` | `['id', 'created_at', ...]` | Fields excluded from audit payload by default |
| `default_ignore_changes` | `array` | `['updated_at']` | Fields ignored when detecting changes |
| `sensitive_fields` | `array` | `['password', 'token', ...]` | Field patterns to redact (case-insensitive) |
| `actor_model` | `string` | `App\Models\User` | Model class for actor relationships |
| `request_logging.enabled` | `bool` | `true` | Enable/disable incoming request logging |
| `request_logging.only_authenticated` | `bool` | `false` | Only log requests from authenticated users |
| `request_logging.exclude_methods` | `array` | `['GET', 'HEAD', 'OPTIONS']` | HTTP methods to exclude from logging |
| `outgoing_request_logging.enabled` | `bool` | `true` | Enable/disable outgoing request logging |
| `outgoing_request_logging.exclude_urls` | `array` | `[]` | URL patterns to exclude (supports `*` wildcards) |
| `retention.delete_after` | `int\|null` | `null` | Days until audit events are deleted |
| `retention.schedule` | `string\|null` | `null` | Auto-schedule: `'daily'`, `'weekly'`, `'monthly'` |
| `request_log_retention.delete_after` | `int\|null` | `null` | Days until request logs are deleted |
| `request_log_retention.schedule` | `string\|null` | `null` | Auto-schedule: `'daily'`, `'weekly'`, `'monthly'` |
| `outgoing_request_log_retention.delete_after` | `int\|null` | `null` | Days until outgoing request logs are deleted |
| `outgoing_request_log_retention.schedule` | `string\|null` | `null` | Auto-schedule: `'daily'`, `'weekly'`, `'monthly'` |

## License

MIT License. See [LICENSE](LICENSE) for details.
