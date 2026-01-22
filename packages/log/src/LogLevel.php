<?php

declare(strict_types=1);

namespace Marko\Log;

enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';

    /**
     * Lower numbers are more severe.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert => 1,
            self::Critical => 2,
            self::Error => 3,
            self::Warning => 4,
            self::Notice => 5,
            self::Info => 6,
            self::Debug => 7,
        };
    }

    /**
     * Check if this level meets the minimum threshold.
     * A level meets the threshold if it's more severe or equal.
     */
    public function meetsThreshold(
        LogLevel $minimum,
    ): bool {
        return $this->severity() <= $minimum->severity();
    }

    /**
     * Get uppercase name for log output.
     */
    public function upperName(): string
    {
        return strtoupper($this->value);
    }
}
