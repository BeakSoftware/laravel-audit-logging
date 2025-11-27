<?php

namespace Lunnar\AuditLogging\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Lunnar\AuditLogging\Models\AuditLog;

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
     * @param  array|null  $metadataExtras  Additional metadata (e.g., ['app_version' => '1.2.3'])
     * @param  string|null  $actorId  Override the actor ID (defaults to authenticated user)
     * @param  CarbonImmutable|null  $createdAt  Override the timestamp
     */
    public static function write(
        string $event,
        array $subjects = [],
        ?array $messageData = null,
        ?array $payload = null,
        ?array $diff = null,
        ?array $metadataExtras = null,
        ?string $actorId = null,
        ?CarbonImmutable $createdAt = null,
    ): AuditLog {
        $now = $createdAt ?: CarbonImmutable::now('UTC');

        // Request metadata (skip for server-generated events)
        $metadata = [];
        if (config('audit-logging.collect_request_metadata', true) && ! app()->runningInConsole()) {
            $metadata = array_filter([
                'accept_language' => Request::header('Accept-Language'),
                'action' => Request::route()?->getActionName(),
                'api_version' => Request::header('Accept-Version'),
                'client_id' => Request::header('X-Client-ID'),
                'csrf_token' => Request::header('X-CSRF-TOKEN'),
                'ip' => Request::ip(),
                'method' => Request::method(),
                'query' => SensitiveDataSanitizer::sanitize(Request::query()),
                'post' => ! Request::isJson() ? SensitiveDataSanitizer::sanitize(Request::post()) : null,
                'json' => Request::isJson() ? SensitiveDataSanitizer::sanitize(Request::json()?->all()) : null,
                'content_type' => Request::header('Content-Type'),
                'content_length' => Request::header('Content-Length'),
                'referer' => Request::header('Referer'),
                'route' => Request::route()?->getName(),
                'session_id' => Request::hasSession() ? Request::session()->getId() : null,
                'ua' => Request::userAgent(),
                'url' => Request::url(),
                'x_forwarded_for' => Request::header('X-Forwarded-For'),
                'x_real_ip' => Request::header('X-Real-IP'),
            ]);
        }

        if ($metadataExtras) {
            $metadata = array_merge($metadata, $metadataExtras);
        }

        // Compute checksum before insert
        $checksum = AuditChecksum::compute([
            'event' => $event,
            'message_data' => $messageData,
            'payload' => $payload,
            'diff' => $diff,
            'actor_id' => $actorId ?? Auth::id(),
            'subjects' => $subjects,
        ]);

        return DB::transaction(function () use ($event, $subjects, $messageData, $payload, $diff, $metadata, $actorId, $now, $checksum) {

            $log = AuditLog::create([
                'event' => $event,
                'message_data' => SensitiveDataSanitizer::sanitize($messageData),
                'payload' => SensitiveDataSanitizer::sanitize($payload),
                'diff' => SensitiveDataSanitizer::sanitize($diff),
                'actor_id' => $actorId ?? Auth::id(),
                'request_id' => Request::header('X-Request-Id'),
                'metadata' => $metadata ?: null,
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
