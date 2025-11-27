<?php

namespace Lunnar\AuditLogging\Support;

class SensitiveDataSanitizer
{
    /**
     * List of sensitive field names that should be redacted.
     *
     * @var array<string>
     */
    private static array $sensitiveFields = [];

    /**
     * Whether the sensitive fields have been initialized from config.
     */
    private static bool $initialized = false;

    /**
     * Initialize sensitive fields from config if not already done.
     */
    private static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$sensitiveFields = config('audit-logging.sensitive_fields', [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'api_secret',
            'secret',
            'secret_key',
            'private_key',
            'public_key',
            'auth_token',
            'bearer_token',
            'authorization',
            'credit_card',
            'card_number',
            'full_number',
            'cvv',
            'cvc',
            'ssn',
            'social_security',
        ]);

        self::$initialized = true;
    }

    /**
     * Sanitize sensitive data by redacting specified fields.
     */
    public static function sanitize(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        self::initialize();

        $sanitized = [];

        foreach ($data as $key => $value) {
            // Check if the key matches any sensitive field (case-insensitive)
            $isSensitive = false;
            foreach (self::$sensitiveFields as $sensitiveField) {
                if (stripos((string) $key, $sensitiveField) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '***';
            } elseif (is_array($value)) {
                // Recursively sanitize nested arrays
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Add custom sensitive field names to the list.
     *
     * @param  array<string>  $fields
     */
    public static function addSensitiveFields(array $fields): void
    {
        self::initialize();
        self::$sensitiveFields = array_unique([...self::$sensitiveFields, ...$fields]);
    }

    /**
     * Get the list of sensitive field names.
     *
     * @return array<string>
     */
    public static function getSensitiveFields(): array
    {
        self::initialize();

        return self::$sensitiveFields;
    }

    /**
     * Reset the sanitizer (useful for testing).
     */
    public static function reset(): void
    {
        self::$sensitiveFields = [];
        self::$initialized = false;
    }
}
