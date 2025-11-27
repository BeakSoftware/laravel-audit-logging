<?php

namespace Lunnar\AuditLogging\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $event
 * @property array|null $message_data
 * @property array|null $payload
 * @property array|null $diff
 * @property string|null $actor_id
 * @property string|null $request_id
 * @property array|null $metadata
 * @property \Carbon\CarbonImmutable $created_at
 * @property string|null $checksum
 */
class AuditLog extends Model
{
    use HasUuids;

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event',
        'message_data',
        'payload',
        'diff',
        'request_id',
        'metadata',
        'actor_id',
        'checksum',
    ];

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'message_data' => 'json:unicode',
        'payload' => 'json:unicode',
        'diff' => 'json:unicode',
        'metadata' => 'json:unicode',
        'created_at' => 'immutable_datetime',
    ];

    /**
     * Get the subjects for the audit log.
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(AuditLogSubject::class);
    }

    /**
     * Get the actor for the audit log.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(config('audit-logging.actor_model', 'App\\Models\\User'), 'actor_id');
    }

    /**
     * Scope the query to only include logs for a specific subject.
     */
    public function scopeForSubject(Builder $q, Model|string $subject, ?string $id = null): Builder
    {
        if ($subject instanceof Model) {
            $type = $subject->getMorphClass();
            $id = $subject->getKey();
        } else {
            if ($id === null) {
                throw new \InvalidArgumentException('ID must be provided when subject is a string');
            }
            $type = $subject;
        }

        return $q->whereHas('subjects', fn ($s) => $s->where('subject_type', $type)->where('subject_id', $id));
    }

    /**
     * Scope the query to only include logs for a specific actor.
     */
    public function scopeForActor(Builder $q, string $actorId): Builder
    {
        return $q->where('actor_id', $actorId);
    }

    /**
     * Scope the query to only include logs for a specific event.
     */
    public function scopeForEvent(Builder $q, string $event): Builder
    {
        return $q->where('event', $event);
    }

    /**
     * Scope the query to only include logs matching an event pattern.
     */
    public function scopeForEventLike(Builder $q, string $pattern): Builder
    {
        return $q->where('event', 'like', $pattern);
    }
}
