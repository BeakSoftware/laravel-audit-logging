<?php

namespace Lunnar\AuditLogging\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lunnar\AuditLogging\Support\Audit;
use ReflectionClass;
use ReflectionMethod;

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
 * protected static bool $auditAutoParentSubjects  - Auto-detect BelongsTo relationships as parent subjects (default: true)
 * protected static array $auditExcludeParents     - BelongsTo relationships to exclude from auto-detection, e.g. ['createdBy', 'country']
 * protected static int $auditLevel                - Default visibility level for this model's audit events (default: config value)
 *
 * Runtime overrides (use within callbacks):
 *
 * Model::withAuditLevel(int $level, callable $callback)     - Temporarily set audit level
 * Model::withAuditEventType(string $type, callable $callback) - Temporarily override event type (e.g. 'init' instead of 'created')
 */
trait HasAuditLogging
{
    /**
     * Cache for BelongsTo relationship metadata per model class.
     * Structure: [ClassName => [['foreignKey' => string, 'subjectType' => string], ...]]
     *
     * @var array<class-string, array<array{foreignKey: string, subjectType: string}>>
     */
    protected static array $belongsToCache = [];

    /**
     * Temporary level override for the current model class.
     *
     * @var array<class-string, int|null>
     */
    protected static array $auditLevelOverride = [];

    /**
     * Temporary event type override for the current model class.
     *
     * @var array<class-string, string|null>
     */
    protected static array $auditEventTypeOverride = [];

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

        // Track added subjects to avoid duplicates
        $addedSubjects = ["{$subjectType}:{$model->getKey()}"];

        // Auto-detect BelongsTo relationships as parent subjects
        if (static::shouldAutoDetectParentSubjects()) {
            foreach (static::getAuditParentSubjects($model) as $parent) {
                $key = "{$parent['subject_type']}:{$parent['subject_id']}";
                if (! in_array($key, $addedSubjects)) {
                    $subjects[] = $parent;
                    $addedSubjects[] = $key;
                }
            }
        }

        // Add any additional subjects (e.g., parent organization)
        foreach (static::getAuditAdditionalSubjects() as $additional) {
            $foreignKey = $additional['foreign_key'];
            $foreignValue = $model->getAttribute($foreignKey);

            if ($foreignValue !== null) {
                $type = $additional['type'];
                $key = "{$type}:{$foreignValue}";

                if (! in_array($key, $addedSubjects)) {
                    $subjects[] = [
                        'subject_type' => $type,
                        'subject_id' => (string) $foreignValue,
                        'role' => $additional['role'] ?? 'related',
                    ];
                    $addedSubjects[] = $key;
                }
            }
        }

        // Check for event type override
        $eventType = static::getAuditEventType($event);

        Audit::write(
            event: "{$eventPrefix}.{$eventType}",
            subjects: $subjects,
            messageData: $messageData,
            payload: $payload,
            diff: $diff,
            level: static::getAuditLevel(),
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
     * Check if automatic parent subject detection is enabled.
     */
    protected static function shouldAutoDetectParentSubjects(): bool
    {
        return static::getStaticPropertyValue('auditAutoParentSubjects', true);
    }

    /**
     * Get BelongsTo relationship names to exclude from auto-detection.
     *
     * @return array<string>
     */
    protected static function getAuditExcludeParents(): array
    {
        return static::getStaticPropertyValue('auditExcludeParents', []);
    }

    /**
     * Get the audit level for this model.
     *
     * Priority: temporary override > model property > config default
     */
    protected static function getAuditLevel(): int
    {
        // Check for temporary override first
        if (isset(static::$auditLevelOverride[static::class])) {
            return static::$auditLevelOverride[static::class];
        }

        $level = static::getStaticPropertyValue('auditLevel');
        if ($level !== null) {
            return $level;
        }

        return config('audit-logging.default_level', 0);
    }

    /**
     * Get the event type, applying any temporary override.
     *
     * Priority: temporary override > original event type
     */
    protected static function getAuditEventType(string $event): string
    {
        if (isset(static::$auditEventTypeOverride[static::class])) {
            return static::$auditEventTypeOverride[static::class];
        }

        return $event;
    }

    /**
     * Auto-detect BelongsTo relationships and return them as parent subjects.
     *
     * @return array<array{subject_type: string, subject_id: string, role: string}>
     */
    protected static function getAuditParentSubjects(Model $model): array
    {
        $class = static::class;

        // Build cache on first call for this model class
        if (! isset(static::$belongsToCache[$class])) {
            static::$belongsToCache[$class] = static::discoverBelongsToRelationships($model);
        }

        // Use cached metadata to build subjects with fresh foreign key values
        $subjects = [];
        foreach (static::$belongsToCache[$class] as $relationship) {
            $foreignValue = $model->getAttribute($relationship['foreignKey']);

            if ($foreignValue !== null) {
                $subjects[] = [
                    'subject_type' => $relationship['subjectType'],
                    'subject_id' => (string) $foreignValue,
                    'role' => 'parent',
                ];
            }
        }

        return $subjects;
    }

    /**
     * Discover BelongsTo relationships via reflection (called once per model class).
     *
     * @return array<array{foreignKey: string, subjectType: string}>
     */
    protected static function discoverBelongsToRelationships(Model $model): array
    {
        $relationships = [];
        $excludeParents = static::getAuditExcludeParents();
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip methods from parent classes (traits, base Model, etc.)
            if ($method->class !== static::class) {
                continue;
            }

            // Skip methods with parameters
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            // Skip excluded relationships
            if (in_array($method->getName(), $excludeParents)) {
                continue;
            }

            // Check return type for BelongsTo hint
            $returnType = $method->getReturnType();
            if ($returnType === null) {
                continue;
            }

            $typeName = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : null;
            if ($typeName !== BelongsTo::class) {
                continue;
            }

            // Call the method to get the relationship metadata
            try {
                $relation = $method->invoke($model);
                if (! $relation instanceof BelongsTo) {
                    continue;
                }

                $relationships[] = [
                    'foreignKey' => $relation->getForeignKeyName(),
                    'subjectType' => static::resolveSubjectTypeForModel($relation->getRelated()),
                ];
            } catch (\Throwable) {
                // Skip if method call fails
                continue;
            }
        }

        return $relationships;
    }

    /**
     * Resolve the subject type for a given model instance.
     */
    protected static function resolveSubjectTypeForModel(Model $model): string
    {
        // Check morph map first
        $morphMap = Relation::morphMap();
        $class = get_class($model);

        foreach ($morphMap as $alias => $mappedClass) {
            if ($mappedClass === $class) {
                return $alias;
            }
        }

        // Fall back to table name
        return $model->getTable();
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

    /**
     * Temporarily set the audit level for this model within the callback.
     */
    public static function withAuditLevel(int $level, callable $callback): mixed
    {
        static::$auditLevelOverride[static::class] = $level;

        try {
            return $callback();
        } finally {
            unset(static::$auditLevelOverride[static::class]);
        }
    }

    /**
     * Temporarily set the audit event type for this model within the callback.
     *
     * This allows overriding the default event type (created, updated, deleted)
     * with a custom one (e.g., 'init' instead of 'created').
     *
     * Example:
     *   Product::withAuditEventType('init', fn () => Product::create([...]));
     *   // Logs as 'product.init' instead of 'product.created'
     */
    public static function withAuditEventType(string $eventType, callable $callback): mixed
    {
        static::$auditEventTypeOverride[static::class] = $eventType;

        try {
            return $callback();
        } finally {
            unset(static::$auditEventTypeOverride[static::class]);
        }
    }
}
