<?php

declare(strict_types=1);

namespace Marko\Log;

use DateTimeImmutable;

readonly class LogRecord
{
    public function __construct(
        public LogLevel $level,
        public string $message,
        public array $context,
        public DateTimeImmutable $datetime,
        public string $channel,
    ) {}

    /**
     * Interpolate context values into message placeholders.
     * PSR-3 style: {key} is replaced with context['key']
     */
    public function interpolatedMessage(): string
    {
        if (empty($this->context)) {
            return $this->message;
        }

        $replacements = [];

        foreach ($this->context as $key => $value) {
            if (is_string($value) || is_numeric($value) || (is_object($value) && method_exists(
                $value,
                '__toString'
            ))) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($this->message, $replacements);
    }

    /**
     * Get context as JSON string for log output.
     */
    public function contextAsJson(): string
    {
        if (empty($this->context)) {
            return '';
        }

        $json = json_encode($this->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : '{}';
    }
}
