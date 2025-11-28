<?php

namespace Lunnar\AuditLogging\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Lunnar\AuditLogging\Models\AuditLogEvent;

class Audit
{
    /**
     * Write an audit log entry.
     *
     * @param  string  $event  The event name (e.g., 'user.created')
     * @param  array{subject_type:string,subject_id:string,role?:string}[]  $subjects  The subjects of the audit log
     * @param  array|null  $messageData  Human-readable data for log messages
     * @param  array|null  $payload  The full payload data
     * @param  array|null  $diff  The diff data for update events
     * @param  string|null  $actorId  Override the actor ID (defaults to authenticated user)
     * @param  CarbonImmutable|null  $createdAt  Override the timestamp
     */
    public static function write(
        string $event,
        array $subjects = [],
        ?array $messageData = null,
        ?array $payload = null,
        ?array $diff = null,
        ?string $actorId = null,
        ?CarbonImmutable $createdAt = null,
    ): AuditLogEvent {
        $now = $createdAt ?: CarbonImmutable::now('UTC');

        // Compute checksum before insert
        $checksum = AuditChecksum::compute([
            'event' => $event,
            'message_data' => $messageData,
            'payload' => $payload,
            'diff' => $diff,
            'actor_id' => $actorId ?? Auth::id(),
            'subjects' => $subjects,
        ]);

        return DB::transaction(function () use ($event, $subjects, $messageData, $payload, $diff, $actorId, $now, $checksum) {

            $log = AuditLogEvent::create([
                'event' => $event,
                'message_data' => SensitiveDataSanitizer::sanitize($messageData),
                'payload' => SensitiveDataSanitizer::sanitize($payload),
                'diff' => SensitiveDataSanitizer::sanitize($diff),
                'actor_id' => $actorId ?? Auth::id(),
                'reference_id' => Request::header('X-Lunnar-Reference-Id'),
                'created_at' => $now,
                'checksum' => $checksum,
            ]);

            if (! empty($subjects)) {
                $log->subjects()->createMany($subjects);
            }

            return $log->load(['actor', 'subjects']);
        });
    }
}
