<?php

declare(strict_types=1);

namespace Marko\Errors;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Deprecated = 'deprecated';

    public static function fromErrorLevel(
        int $level,
    ): self {
        return match ($level) {
            E_ERROR, E_USER_ERROR => self::Error,
            E_WARNING, E_USER_WARNING => self::Warning,
            E_NOTICE, E_USER_NOTICE => self::Notice,
            E_DEPRECATED, E_USER_DEPRECATED => self::Deprecated,
            default => self::Error,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Error => 'Error',
            self::Warning => 'Warning',
            self::Notice => 'Notice',
            self::Deprecated => 'Deprecated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Error => "\033[31m",
            self::Warning => "\033[33m",
            self::Notice => "\033[36m",
            self::Deprecated => "\033[35m",
        };
    }
}
