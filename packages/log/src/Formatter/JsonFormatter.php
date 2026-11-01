<?php

declare(strict_types=1);

namespace Marko\Log\Formatter;

use DateTimeInterface;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\LogRecord;
use stdClass;
use Stringable;
use Throwable;

/**
 * Writes each record as one JSON object per line, for log collectors (Docker, Kubernetes, CloudWatch,
 * Loki, ...). Context is normalized first, so a value that JSON cannot encode is replaced, never fatal.
 */
readonly class JsonFormatter implements LogFormatterInterface
{
    public const string MAX_DEPTH_REACHED = '[max depth reached]';

    public const int DEFAULT_MAX_DEPTH = 10;

    /**
     * JSON escapes newlines, C0 controls and U+2028/U+2029 itself, so one record is always one line.
     */
    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR;

    private ContextRedactor $redactor;

    /**
     * @param list<string> $redactKeys Context keys (case-insensitive, at any depth) whose values are replaced with [redacted]
     * @param int $maxDepth Nesting depth after which context values are replaced with [max depth reached]
     */
    public function __construct(
        array $redactKeys = ContextRedactor::DEFAULT_KEYS,
        private int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ) {
        $this->redactor = new ContextRedactor($redactKeys);
    }

    public function format(
        LogRecord $record,
    ): string {
        $context = [];

        foreach ($record->context as $key => $value) {
            $context[$key] = $this->normalize($value, 2);
        }

        // Normalizing first bounds the depth, so redaction never walks a recursive array.
        $record = $this->redactor->redact(new LogRecord(
            level: $record->level,
            message: $record->message,
            context: $context,
            datetime: $record->datetime,
            channel: $record->channel,
        ));

        $json = json_encode([
            'datetime' => $record->datetime->format(DateTimeInterface::RFC3339_EXTENDED),
            'level' => $record->level->value,
            'channel' => $record->channel,
            'message' => $record->interpolatedMessage(),
            'context' => $record->context === [] ? new stdClass() : $record->context,
        ], self::JSON_FLAGS);

        return $json . "\n";
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>|string
     */
    private function normalizeArray(
        array $values,
        int $depth,
    ): array|string {
        if ($depth > $this->maxDepth) {
            return self::MAX_DEPTH_REACHED;
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[$key] = $this->normalize($value, $depth + 1);
        }

        return $normalized;
    }

    private function normalize(
        mixed $value,
        int $depth,
    ): mixed {
        return match (true) {
            is_array($value) => $this->normalizeArray($value, $depth),
            is_float($value) && is_nan($value) => 'NAN',
            is_float($value) && is_infinite($value) => $value > 0 ? 'INF' : '-INF',
            $value instanceof Throwable => $this->normalizeThrowable($value, $depth),
            $value instanceof Stringable => (string) $value,
            is_object($value) => $value::class,
            is_resource($value), get_debug_type($value) === 'resource (closed)' => get_debug_type($value),
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>|string
     */
    private function normalizeThrowable(
        Throwable $throwable,
        int $depth,
    ): array|string {
        if ($depth > $this->maxDepth) {
            return self::MAX_DEPTH_REACHED;
        }

        $normalized = [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
        ];

        $previous = $throwable->getPrevious();

        if ($previous !== null) {
            $normalized['previous'] = $this->normalizeThrowable($previous, $depth + 1);
        }

        return $normalized;
    }
}
