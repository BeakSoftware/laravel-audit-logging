<?php

namespace Lunnar\AuditLogging\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $audit_log_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $role
 */
class AuditLogSubject extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'audit_log_id',
        'subject_type',
        'subject_id',
        'role',
    ];

    /**
     * Get the log that the subject belongs to.
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }

    /**
     * Get the subject model (User, Organization, etc.).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
