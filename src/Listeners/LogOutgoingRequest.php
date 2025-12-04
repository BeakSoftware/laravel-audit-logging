<?php

namespace Lunnar\AuditLogging\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Str;
use Lunnar\AuditLogging\Models\AuditLogOutgoingRequest;
use Lunnar\AuditLogging\Support\SensitiveDataSanitizer;
use WeakMap;

class LogOutgoingRequest
{
    /**
     * Track pending requests and their log record IDs.
     *
     * @var WeakMap<Request, array{id: string, start_time: float}>
     */
    protected static WeakMap $pendingRequests;

    /**
     * Handle the RequestSending event.
     */
    public function handleRequestSending(RequestSending $event): void
    {
        if (! config('audit-logging.outgoing_request_logging.enabled', true)) {
            return;
        }

        if ($this->shouldExclude($event->request->url())) {
            return;
        }

        $logRecordId = (string) Str::uuid();
        $startTime = microtime(true);

        // Store tracking info
        $this->getPendingRequests()->offsetSet($event->request, [
            'id' => $logRecordId,
            'start_time' => $startTime,
        ]);

        AuditLogOutgoingRequest::query()->insert([
            'id' => $logRecordId,
            'method' => $event->request->method(),
            'url' => $event->request->url(),
            'reference_id' => $this->getReferenceId(),
            'request_headers' => $this->encodeJson($this->sanitizeHeaders($event->request->headers())),
            'request_body' => $this->encodeJson($this->getRequestBody($event->request)),
        ]);
    }

    /**
     * Handle the ResponseReceived event.
     */
    public function handleResponseReceived(ResponseReceived $event): void
    {
        $pending = $this->getPendingRequests();

        if (! $pending->offsetExists($event->request)) {
            return;
        }

        $tracking = $pending->offsetGet($event->request);
        $pending->offsetUnset($event->request);

        $durationMs = (microtime(true) - $tracking['start_time']) * 1000;

        AuditLogOutgoingRequest::query()
            ->where('id', $tracking['id'])
            ->update([
                'status_code' => $event->response->status(),
                'duration_ms' => round($durationMs, 2),
                'response_body' => $this->encodeJson($this->getResponseBody($event->response)),
            ]);
    }

    /**
     * Handle the ConnectionFailed event.
     */
    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        $pending = $this->getPendingRequests();

        if (! $pending->offsetExists($event->request)) {
            return;
        }

        $tracking = $pending->offsetGet($event->request);
        $pending->offsetUnset($event->request);

        $durationMs = (microtime(true) - $tracking['start_time']) * 1000;

        AuditLogOutgoingRequest::query()
            ->where('id', $tracking['id'])
            ->update([
                'duration_ms' => round($durationMs, 2),
                'error_message' => 'Connection failed',
            ]);
    }

    /**
     * Get the pending requests WeakMap.
     *
     * @return WeakMap<Request, array{id: string, start_time: float}>
     */
    protected function getPendingRequests(): WeakMap
    {
        if (! isset(self::$pendingRequests)) {
            self::$pendingRequests = new WeakMap;
        }

        return self::$pendingRequests;
    }

    /**
     * Check if the URL should be excluded from logging.
     */
    protected function shouldExclude(string $url): bool
    {
        $excludePatterns = config('audit-logging.outgoing_request_logging.exclude_urls', []);

        foreach ($excludePatterns as $pattern) {
            if (Str::is($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current request's reference ID.
     */
    protected function getReferenceId(): ?string
    {
        $request = request();

        if (! $request) {
            return null;
        }

        return $request->header('X-Lunnar-Reference-Id');
    }

    /**
     * Sanitize request headers, removing sensitive values.
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'api-key',
        ];

        $sanitized = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                // Headers from Guzzle are typically strings, not arrays
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get and sanitize the request body.
     */
    protected function getRequestBody(Request $request): ?array
    {
        $body = $request->body();

        if (empty($body)) {
            return null;
        }

        // Try to decode as JSON
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, try to parse as form data
            parse_str($body, $decoded);

            if (empty($decoded)) {
                return null;
            }
        }

        return SensitiveDataSanitizer::sanitize($decoded);
    }

    /**
     * Get and sanitize the response body.
     */
    protected function getResponseBody($response): ?array
    {
        $body = $response->body();

        if (empty($body)) {
            return null;
        }

        // Only attempt to parse JSON responses
        $contentType = $response->header('Content-Type') ?? '';
        if (! str_contains($contentType, 'application/json')) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return SensitiveDataSanitizer::sanitize($decoded);
    }

    /**
     * Encode a value to JSON for storage, preserving unicode characters.
     */
    protected function encodeJson(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
