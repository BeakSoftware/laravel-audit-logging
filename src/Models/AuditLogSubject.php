<?php

namespace Lunnar\AuditLogging\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lunnar\AuditLogging\Database\Factories\AuditLogSubjectFactory;

/**
 * @property string $id
 * @property string $audit_log_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $role
 */
class AuditLogSubject extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): AuditLogSubjectFactory
    {
        return AuditLogSubjectFactory::new();
    }

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


    /**
     * Get a formatted version of the subject for display.
     *
     * The subject model can define a `toAuditSubject()` method to customize
     * the data returned, including transformations.
     */
    public function getFormattedSubjectAttribute(): ?array
    {
        if (! $this->relationLoaded('subject') || ! $this->subject) {
            return null;
        }

        if (method_exists($this->subject, 'toAuditSubject')) {
            return $this->subject->toAuditSubject();
        }

        return null;
    }
}
