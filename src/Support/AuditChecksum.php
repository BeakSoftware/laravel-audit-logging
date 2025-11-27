<?php

namespace Lunnar\AuditLogging\Support;

use RuntimeException;

class AuditChecksum
{
    /**
     * Compute a cryptographically secure HMAC checksum over canonical fields.
     */
    public static function compute(array $payload): string
    {
        // Only include stable keys, with stable ordering
        $canonical = [
            'event' => $payload['event'] ?? null,
            'message_data' => self::stableJson($payload['message_data'] ?? null),
            'payload' => self::stableJson($payload['payload'] ?? null),
            'diff' => self::stableJson($payload['diff'] ?? null),
            'actor_id' => $payload['actor_id'] ?? null,
            'subjects' => array_map(fn ($s) => [
                'subject_type' => $s['subject_type'] ?? null,
                'subject_id' => $s['subject_id'] ?? null,
                'role' => $s['role'] ?? null,
            ], $payload['subjects'] ?? []),
        ];

        $secret = config('audit-logging.audit_key');

        if (empty($secret)) {
            throw new RuntimeException('AUDIT_KEY not configured. Please set it in your .env file.');
        }

        return hash_hmac('sha256', self::stableJson($canonical), $secret);
    }

    /**
     * Verify that a checksum is valid for the given payload.
     */
    public static function verify(array $payload, string $expectedChecksum): bool
    {
        $computed = self::compute($payload);

        // Use hash_equals for timing-attack safe comparison
        return hash_equals($expectedChecksum, $computed);
    }

    private static function stableJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
