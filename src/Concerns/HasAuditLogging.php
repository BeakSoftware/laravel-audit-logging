<?php

namespace Lunnar\AuditLogging\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lunnar\AuditLogging\Support\Audit;
use ReflectionClass;

/**
 * Trait for automatic audit logging on Eloquent models.
 *
 * Configure via static properties on the model:
 *
 * protected static array $auditExclude            - Fields to exclude from payload (default: id, created_at, updated_at, deleted_at)
 * protected static array $auditMessageFields      - Fields to include in messageData, supports dot notation
 *                                                   e.g. ['name', 'email' => 'email_masked'] uses email_masked value with 'email' key
 * protected static array $auditIgnoreChanges      - Fields to ignore when detecting changes (default: updated_at)
 * protected static array $auditEvents             - Events to log: ['created', 'updated', 'deleted'] (default: all)
 * protected static string $auditEventPrefix       - Custom event prefix (default: derived from morph map or table name)
 * protected static string $auditSubjectType       - Custom subject type (default: derived from morph map or table name)
 * protected static array $auditAdditionalSubjects - Additional subjects to include, e.g. [['type' => 'organizations', 'foreign_key' => 'organization_id', 'role' => 'parent']]
 */
trait HasAuditLogging
{
    /**
     * Boot the trait and register model event listeners.
     */
    protected static function bootHasAuditLogging(): void
    {
        $events = static::getAuditEvents();

        if (in_array('created', $events)) {
            static::created(function (Model $model) {
                static::writeAuditLog($model, 'created');
            });
        }

        if (in_array('updated', $events)) {
            static::updated(function (Model $model) {
                static::writeAuditLog($model, 'updated');
            });
        }

        if (in_array('deleted', $events)) {
            static::deleted(function (Model $model) {
                static::writeAuditLog($model, 'deleted');
            });
        }
    }

    /**
     * Write an audit log entry for the given event.
     */
    protected static function writeAuditLog(Model $model, string $event): void
    {
        $eventPrefix = static::getAuditEventPrefix();
        $subjectType = static::getAuditSubjectType();
        $excludeFields = static::getAuditExcludeFields();
        $ignoreChanges = static::getAuditIgnoreChanges();

        // For updates, check if there are meaningful changes
        $diff = null;
        if ($event === 'updated') {
            $changes = collect($model->getChanges())->except($ignoreChanges);
            if ($changes->isEmpty()) {
                return;
            }

            $diff = $changes->mapWithKeys(fn ($new, $field) => [
                $field => [$model->getOriginal($field), $new],
            ])->except($excludeFields)->all();
        }

        // Build messageData from configured fields
        $messageData = static::buildAuditMessageData($model);

        // Build payload (exclude configured fields)
        $payload = collect($model->toArray())->except($excludeFields)->all();

        // Build subjects array (primary + additional)
        $subjects = [
            [
                'subject_type' => $subjectType,
                'subject_id' => (string) $model->getKey(),
                'role' => 'primary',
            ],
        ];

        // Add any additional subjects (e.g., parent organization)
        foreach (static::getAuditAdditionalSubjects() as $additional) {
            $foreignKey = $additional['foreign_key'];
            $foreignValue = $model->getAttribute($foreignKey);

            if ($foreignValue !== null) {
                $subjects[] = [
                    'subject_type' => $additional['type'],
                    'subject_id' => (string) $foreignValue,
                    'role' => $additional['role'] ?? 'related',
                ];
            }
        }

        Audit::write(
            event: "{$eventPrefix}.{$event}",
            subjects: $subjects,
            messageData: $messageData,
            payload: $payload,
            diff: $diff,
        );
    }

    /**
     * Build the messageData array from configured fields.
     */
    protected static function buildAuditMessageData(Model $model): array
    {
        $fields = static::getAuditMessageFields();
        $data = [];

        foreach ($fields as $key => $field) {
            // Support ['name', 'email' => 'email_masked'] syntax
            if (is_numeric($key)) {
                $key = $field;
            }

            // Support dot notation for nested values
            $data[$key] = data_get($model, $field);
        }

        return $data;
    }

    /**
     * Get a static property value from the class using reflection.
     */
    protected static function getStaticPropertyValue(string $property, mixed $default = null): mixed
    {
        $reflection = new ReflectionClass(static::class);

        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            if ($prop->isStatic()) {
                return $prop->getValue();
            }
        }

        return $default;
    }

    /**
     * Get fields to exclude from audit payload.
     *
     * @return array<string>
     */
    protected static function getAuditExcludeFields(): array
    {
        return static::getStaticPropertyValue(
            'auditExclude',
            config('audit-logging.default_exclude', ['id', 'created_at', 'updated_at', 'deleted_at'])
        );
    }

    /**
     * Get fields to include in messageData.
     *
     * @return array<int|string, string>
     */
    protected static function getAuditMessageFields(): array
    {
        return static::getStaticPropertyValue('auditMessageFields', []);
    }

    /**
     * Get fields to ignore when detecting changes.
     *
     * @return array<string>
     */
    protected static function getAuditIgnoreChanges(): array
    {
        return static::getStaticPropertyValue(
            'auditIgnoreChanges',
            config('audit-logging.default_ignore_changes', ['updated_at'])
        );
    }

    /**
     * Get which events to log.
     *
     * @return array<string>
     */
    protected static function getAuditEvents(): array
    {
        return static::getStaticPropertyValue('auditEvents', ['created', 'updated', 'deleted']);
    }

    /**
     * Get the event prefix (e.g., 'product' for 'product.created').
     */
    protected static function getAuditEventPrefix(): string
    {
        $prefix = static::getStaticPropertyValue('auditEventPrefix');
        if ($prefix !== null) {
            return $prefix;
        }

        // Try to get from morph map, fall back to table name singular
        $morphMap = Relation::morphMap();
        $class = static::class;

        foreach ($morphMap as $alias => $mappedClass) {
            if ($mappedClass === $class) {
                // Convert 'products' to 'product'
                return rtrim($alias, 's');
            }
        }

        // Fall back to table name singular
        return str((new static)->getTable())->singular()->toString();
    }

    /**
     * Get the subject type for audit log entries.
     */
    protected static function getAuditSubjectType(): string
    {
        $subjectType = static::getStaticPropertyValue('auditSubjectType');
        if ($subjectType !== null) {
            return $subjectType;
        }

        // Try to get from morph map, fall back to table name
        $morphMap = Relation::morphMap();
        $class = static::class;

        foreach ($morphMap as $alias => $mappedClass) {
            if ($mappedClass === $class) {
                return $alias;
            }
        }

        // Fall back to table name
        return (new static)->getTable();
    }

    /**
     * Get additional subjects to include in audit logs.
     * Format: [['type' => 'organizations', 'foreign_key' => 'organization_id', 'role' => 'parent'], ...]
     *
     * @return array<array{type: string, foreign_key: string, role?: string}>
     */
    protected static function getAuditAdditionalSubjects(): array
    {
        return static::getStaticPropertyValue('auditAdditionalSubjects', []);
    }

    /**
     * Temporarily disable audit logging for this model.
     */
    public static function withoutAuditLogging(callable $callback): mixed
    {
        $dispatcher = static::getEventDispatcher();
        static::unsetEventDispatcher();

        try {
            return $callback();
        } finally {
            static::setEventDispatcher($dispatcher);
        }
    }
}
