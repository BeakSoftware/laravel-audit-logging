<?php

namespace Lunnar\AuditLogging\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $method
 * @property string $url
 * @property string|null $route_name
 * @property string|null $route_action
 * @property int|null $status_code
 * @property float|null $duration_ms
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $session_id
 * @property string|null $actor_id
 * @property string $reference_id
 * @property array|null $request_headers
 * @property array|null $request_query
 * @property array|null $request_body
 * @property array|null $response_body
 * @property \Carbon\CarbonImmutable $created_at
 */
class AuditLogRequest extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'audit_log_requests';

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'method',
        'url',
        'route_name',
        'route_action',
        'status_code',
        'duration_ms',
        'ip',
        'user_agent',
        'session_id',
        'actor_id',
        'reference_id',
        'request_headers',
        'request_query',
        'request_body',
        'response_body',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status_code' => 'integer',
        'duration_ms' => 'float',
        'request_headers' => 'json:unicode',
        'request_query' => 'json:unicode',
        'request_body' => 'json:unicode',
        'response_body' => 'json:unicode',
        'created_at' => 'immutable_datetime',
    ];

    /**
     * Get the actor for the request.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(config('audit-logging.actor_model', 'App\\Models\\User'), 'actor_id');
    }

    /**
     * Get the audit log events associated with this request via reference_id.
     */
    public function events(): Collection
    {
        return AuditLogEvent::where('reference_id', $this->reference_id)->get();
    }

    /**
     * Scope the query to only include requests for a specific reference ID.
     */
    public function scopeForReferenceId(Builder $q, string $referenceId): Builder
    {
        return $q->where('reference_id', $referenceId);
    }

    /**
     * Scope the query to only include requests for a specific actor.
     */
    public function scopeForActor(Builder $q, string $actorId): Builder
    {
        return $q->where('actor_id', $actorId);
    }

    /**
     * Scope the query to only include requests for a specific route.
     */
    public function scopeForRoute(Builder $q, string $routeName): Builder
    {
        return $q->where('route_name', $routeName);
    }

    /**
     * Scope the query to only include requests with a specific HTTP method.
     */
    public function scopeForMethod(Builder $q, string $method): Builder
    {
        return $q->where('method', strtoupper($method));
    }

    /**
     * Scope the query to only include requests with a specific status code.
     */
    public function scopeForStatusCode(Builder $q, int $statusCode): Builder
    {
        return $q->where('status_code', $statusCode);
    }

    /**
     * Scope the query to only include failed requests (4xx and 5xx status codes).
     */
    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status_code', '>=', 400);
    }

    /**
     * Scope the query to only include successful requests (2xx status codes).
     */
    public function scopeSuccessful(Builder $q): Builder
    {
        return $q->whereBetween('status_code', [200, 299]);
    }
}
