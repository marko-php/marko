<?php

declare(strict_types=1);

namespace Marko\Log\Formatter;

use Marko\Log\LogRecord;

/**
 * Replaces the values of sensitive context keys with [redacted]. Shared by every formatter, so
 * 'log.redact_keys' masks the same keys whichever format is written.
 */
readonly class ContextRedactor
{
    public const string REDACTED = '[redacted]';

    public const array DEFAULT_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'authorization',
    ];

    /** @var array<string, true> */
    private array $keys;

    /**
     * @param list<string> $keys Context keys (case-insensitive, at any depth) whose values are replaced with [redacted]
     */
    public function __construct(
        array $keys = self::DEFAULT_KEYS,
    ) {
        $normalized = [];

        foreach ($keys as $key) {
            $normalized[strtolower($key)] = true;
        }

        $this->keys = $normalized;
    }

    /**
     * Redaction runs before interpolation, so a redacted key used as a {placeholder} is masked too.
     */
    public function redact(
        LogRecord $record,
    ): LogRecord {
        if ($this->keys === [] || $record->context === []) {
            return $record;
        }

        return new LogRecord(
            level: $record->level,
            message: $record->message,
            context: $this->redactArray($record->context),
            datetime: $record->datetime,
            channel: $record->channel,
        );
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private function redactArray(
        array $values,
    ): array {
        foreach ($values as $key => $value) {
            if (is_string($key) && isset($this->keys[strtolower($key)])) {
                $values[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $values[$key] = $this->redactArray($value);
            }
        }

        return $values;
    }
}
