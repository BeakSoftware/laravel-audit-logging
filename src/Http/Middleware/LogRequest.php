<?php

namespace Lunnar\AuditLogging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lunnar\AuditLogging\Models\AuditLogRequest;
use Lunnar\AuditLogging\Support\SensitiveDataSanitizer;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    /**
     * The start time of the request.
     */
    protected float $startTime;

    /**
     * The logged request record ID.
     */
    protected ?int $logRecordId = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->startTime = microtime(true);

        // Skip logging if only_authenticated is enabled and no user is logged in
        if (config('audit-logging.request_logging.only_authenticated', false) && Auth::id() === null) {
            return $next($request);
        }

        // Log request immediately so it's captured even if the request crashes
        $this->logRecordId = AuditLogRequest::query()->insertGetId([
            'method' => $request->method(),
            'url' => $request->url(),
            'route_name' => $request->route()?->getName(),
            'route_action' => $request->route()?->getActionName(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'actor_id' => Auth::id(),
            'reference_id' => $request->header('X-Lunnar-Reference-Id'),
            'request_headers' => $this->sanitizeHeaders($request->headers->all()),
            'request_query' => $this->getRequestQuery($request),
            'request_body' => $this->getRequestBody($request),
        ]);

        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->logRecordId) {
            return;
        }

        $durationMs = (microtime(true) - $this->startTime) * 1000;

        AuditLogRequest::query()
            ->where('id', $this->logRecordId)
            ->update([
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round($durationMs, 2),
                'response_body' => $this->getResponseBody($response),
            ]);
    }

    /**
     * Sanitize request headers, removing sensitive values and duplicates.
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ];

        // Headers already stored in dedicated columns
        $excludeHeaders = [
            'user-agent',
            'x-lunnar-reference-id',
        ];

        $sanitized = [];
        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $excludeHeaders)) {
                continue;
            }

            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = ['[REDACTED]'];
            } else {
                $sanitized[$key] = $values;
            }
        }

        return $sanitized;
    }

    /**
     * Get and sanitize query string parameters.
     */
    protected function getRequestQuery(Request $request): ?array
    {
        $query = $request->query();

        if (empty($query)) {
            return null;
        }

        return SensitiveDataSanitizer::sanitize($query);
    }

    /**
     * Get and sanitize the request body.
     */
    protected function getRequestBody(Request $request): ?array
    {
        $body = null;

        if ($request->isJson()) {
            $body = $request->json()?->all();
        } else {
            $body = $request->post();
        }

        if (empty($body)) {
            return null;
        }

        return SensitiveDataSanitizer::sanitize($body);
    }

    /**
     * Get and sanitize the response body.
     */
    protected function getResponseBody(Response $response): ?array
    {
        $content = $response->getContent();

        if (empty($content)) {
            return null;
        }

        // Only attempt to parse JSON responses
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'application/json')) {
            return null;
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return SensitiveDataSanitizer::sanitize($decoded);
    }
}
