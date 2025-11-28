<?php

namespace Lunnar\AuditLogging\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Lunnar\AuditLogging\Models\AuditLogEvent;
use Lunnar\AuditLogging\Models\AuditLogSubject;

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
    ): void {
        $now = $createdAt ?: CarbonImmutable::now('UTC');
        $actorId = $actorId ?? Auth::id();
        $eventId = Str::uuid()->toString();

        // Compute checksum before insert
        $checksum = AuditChecksum::compute([
            'event' => $event,
            'message_data' => $messageData,
            'payload' => $payload,
            'diff' => $diff,
            'actor_id' => $actorId,
            'subjects' => $subjects,
        ]);

        // Sanitize and JSON encode array fields
        $sanitizedMessageData = SensitiveDataSanitizer::sanitize($messageData);
        $sanitizedPayload = SensitiveDataSanitizer::sanitize($payload);
        $sanitizedDiff = SensitiveDataSanitizer::sanitize($diff);

        DB::transaction(function () use ($event, $subjects, $sanitizedMessageData, $sanitizedPayload, $sanitizedDiff, $actorId, $now, $checksum, $eventId) {
            AuditLogEvent::insert([
                'id' => $eventId,
                'event' => $event,
                'message_data' => $sanitizedMessageData ? json_encode($sanitizedMessageData, JSON_UNESCAPED_UNICODE) : null,
                'payload' => $sanitizedPayload ? json_encode($sanitizedPayload, JSON_UNESCAPED_UNICODE) : null,
                'diff' => $sanitizedDiff ? json_encode($sanitizedDiff, JSON_UNESCAPED_UNICODE) : null,
                'actor_id' => $actorId,
                'reference_id' => Request::header('X-Lunnar-Reference-Id'),
                'created_at' => $now->format('Y-m-d H:i:s.uP'),
                'checksum' => $checksum,
            ]);

            if (! empty($subjects)) {
                AuditLogSubject::insert(array_map(fn ($s) => [
                    'id' => Str::uuid()->toString(),
                    'audit_log_id' => $eventId,
                    'subject_type' => $s['subject_type'],
                    'subject_id' => $s['subject_id'],
                    'role' => $s['role'] ?? 'primary',
                ], $subjects));
            }
        });
    }
}
