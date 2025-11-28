# Laravel Audit Logging

Automatic audit logging for Laravel Eloquent models via a simple trait.

## Features

- 🔄 Automatic logging of `created`, `updated`, and `deleted` events
- 🎯 Per-model configuration via static properties
- 🔒 Automatic sanitization of sensitive data (passwords, tokens, etc.)
- 🔐 HMAC checksum for data integrity verification
- 📊 Rich metadata capture (IP, user agent, route, etc.)
- 🔗 Support for multiple subjects per audit entry (e.g., parent relationships)
- 🗑️ Configurable retention policy for automatic cleanup

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

## Configuration Options

All configuration is done via static properties on your model:

| Property                   | Type     | Default                                            | Description                                                        |
| -------------------------- | -------- | -------------------------------------------------- | ------------------------------------------------------------------ |
| `$auditExclude`            | `array`  | `['id', 'created_at', 'updated_at', 'deleted_at']` | Fields to exclude from payload                                     |
| `$auditMessageFields`      | `array`  | `[]`                                               | Fields for messageData (supports `['field' => 'accessor']` syntax) |
| `$auditIgnoreChanges`      | `array`  | `['updated_at']`                                   | Fields to ignore when detecting changes                            |
| `$auditEvents`             | `array`  | `['created', 'updated', 'deleted']`                | Which events to log                                                |
| `$auditEventPrefix`        | `string` | _from morph map/table_                             | Event prefix (e.g., `product`)                                     |
| `$auditSubjectType`        | `string` | _from morph map/table_                             | Subject type for audit entries                                     |
| `$auditAdditionalSubjects` | `array`  | `[]`                                               | Additional related subjects                                        |

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

### Additional Subjects (Parent Relationships)

For models that belong to a parent (e.g., roles belonging to organizations):

```php
class Role extends Model
{
    use HasAuditLogging;

    protected static array $auditMessageFields = ['name'];

    // Include the parent organization in audit entries
    protected static array $auditAdditionalSubjects = [
        [
            'type' => 'organizations',       // Subject type
            'foreign_key' => 'organization_id',  // Foreign key on this model
            'role' => 'parent'               // Role in the audit entry
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

## Querying Audit Logs

```php
use Lunnar\AuditLogging\Models\AuditLog;

// Get all logs for a specific model
$logs = AuditLog::forSubject($product)->latest('created_at')->get();

// Get all logs by a specific actor
$logs = AuditLog::forActor($userId)->get();

// Get all logs for a specific event
$logs = AuditLog::forEvent('product.created')->get();

// Get all logs matching an event pattern
$logs = AuditLog::forEventLike('product.%')->get();
```

## Verifying Checksum Integrity

```php
use Lunnar\AuditLogging\Support\AuditChecksum;

$log = AuditLog::find($id);

$isValid = AuditChecksum::verify([
    'event' => $log->event,
    'message_data' => $log->message_data,
    'payload' => $log->payload,
    'diff' => $log->diff,
    'actor_id' => $log->actor_id,
    'subjects' => $log->subjects->map->only(['subject_type', 'subject_id', 'role'])->all(),
], $log->checksum);
```

## Retention Policy

The package includes a retention policy feature for automatically deleting old audit logs.

### Configuration

In `config/audit-logging.php`:

```php
'retention' => [
    'delete_after' => 365,   // Delete logs after 1 year
    'schedule' => 'daily',   // Automatically run daily at 3:00 AM
],
```

Options:
- `delete_after`: Days until logs are deleted. Set to `null` to disable.
- `schedule`: `'daily'`, `'weekly'`, `'monthly'`, or `null` to disable automatic scheduling.

### Running Manually

You can also run the command manually:

```bash
php artisan audit:retention
```

## Configuration

Publish the config file to customize defaults:

```bash
php artisan vendor:publish --tag=audit-logging-config
```

See `config/audit-logging.php` for all available options.

## License

MIT License. See [LICENSE](LICENSE) for details.
