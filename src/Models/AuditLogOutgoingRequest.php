<?php

namespace Lunnar\AuditLogging\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $method
 * @property string $url
 * @property int|null $status_code
 * @property float|null $duration_ms
 * @property string|null $reference_id
 * @property array|null $request_headers
 * @property array|null $request_body
 * @property array|null $response_body
 * @property string|null $error_message
 * @property \Carbon\CarbonImmutable $created_at
 */
class AuditLogOutgoingRequest extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'audit_log_outgoing_requests';

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'method',
        'url',
        'status_code',
        'duration_ms',
        'reference_id',
        'request_headers',
        'request_body',
        'response_body',
        'error_message',
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
        'request_body' => 'json:unicode',
        'response_body' => 'json:unicode',
        'created_at' => 'immutable_datetime',
    ];

    /**
     * Get the audit log events associated with this outgoing request via reference_id.
     */
    public function events(): HasMany
    {
        return $this->hasMany(AuditLogEvent::class, 'reference_id', 'reference_id');
    }

    /**
     * Scope the query to only include requests for a specific reference ID.
     */
    public function scopeForReferenceId(Builder $q, string $referenceId): Builder
    {
        return $q->where('reference_id', $referenceId);
    }

    /**
     * Scope the query to only include requests matching a URL pattern.
     */
    public function scopeForUrl(Builder $q, string $url): Builder
    {
        return $q->where('url', 'like', "%{$url}%");
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
     * Scope the query to only include failed requests (4xx and 5xx status codes or connection errors).
     */
    public function scopeFailed(Builder $q): Builder
    {
        return $q->where(function (Builder $q) {
            $q->where('status_code', '>=', 400)
                ->orWhereNotNull('error_message');
        });
    }

    /**
     * Scope the query to only include successful requests (2xx status codes).
     */
    public function scopeSuccessful(Builder $q): Builder
    {
        return $q->whereBetween('status_code', [200, 299])
            ->whereNull('error_message');
    }
}
