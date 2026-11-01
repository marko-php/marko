<?php

declare(strict_types=1);

namespace Marko\Log\Exceptions;

class InvalidLogConfigException extends LogException
{
    public static function invalidMode(
        string $key,
        int $mode,
    ): self {
        $given = sprintf('0%o', $mode);

        return new self(
            message: "Invalid permission mode $given for '$key'",
            context: "Configured value: $mode (octal $given)",
            suggestion: "Set '$key' to an octal permission mode between 0 and 0777, e.g. 0600 for files or 0700 for directories",
        );
    }

    public static function invalidRedactKey(
        mixed $key,
    ): self {
        $type = get_debug_type($key);

        return new self(
            message: "Invalid entry in 'log.redact_keys': expected a non-empty string, got $type",
            context: "Configured entry type: $type",
            suggestion: "List context keys to redact as non-empty strings, e.g. ['password', 'token']",
        );
    }

    /**
     * @param list<string> $installed
     */
    public static function noHandlers(
        array $installed,
    ): self {
        return new self(
            message: "No log handler configured: 'log.handlers' is empty",
            context: 'Installed handlers: ' . self::handlerList($installed),
            suggestion: 'Set LOG_HANDLERS (or \'log.handlers\' in config/log.php) to one or more installed handlers, e.g. LOG_HANDLERS=' . ($installed[0] ?? 'file'),
        );
    }

    /**
     * @param list<string> $installed
     */
    public static function unknownHandler(
        string $name,
        array $installed,
    ): self {
        return new self(
            message: "Unknown log handler '$name' in 'log.handlers'",
            context: 'Installed handlers: ' . self::handlerList($installed),
            suggestion: "Use one of the installed handlers, or install the package that provides '$name' (for example marko/log-file for 'file' or marko/log-stream for 'stream')",
        );
    }

    public static function duplicateHandler(
        string $name,
    ): self {
        return new self(
            message: "Log handler '$name' is listed more than once in 'log.handlers'",
            context: "Each listed handler receives every record, so '$name' would write every record twice",
            suggestion: "List '$name' once in LOG_HANDLERS (or 'log.handlers')",
        );
    }

    public static function invalidHandlerName(
        mixed $name,
    ): self {
        $type = get_debug_type($name);

        return new self(
            message: "Invalid entry in 'log.handlers': expected a non-empty string, got $type",
            context: "Configured entry type: $type",
            suggestion: 'List handler names as non-empty strings, e.g. LOG_HANDLERS=file,stream',
        );
    }

    public static function invalidHandlerClass(
        string $name,
        mixed $class,
    ): self {
        $given = is_string($class) ? $class : get_debug_type($class);

        return new self(
            message: "Log handler '$name' maps to $given, which does not implement LogHandlerInterface",
            context: "'log.handler_map' entry: '$name' => $given",
            suggestion: "Map '$name' to a class implementing Marko\\Log\\Contracts\\LogHandlerInterface in 'log.handler_map'",
        );
    }

    public static function unknownFormatter(
        string $formatter,
    ): self {
        return new self(
            message: "Unknown log formatter '$formatter'",
            context: "Configured 'log.formatter': $formatter",
            suggestion: "Set LOG_FORMATTER (or 'log.formatter') to 'line' or 'json'",
        );
    }

    public static function loggerWithoutHandlers(): self
    {
        return new self(
            message: 'A Logger needs at least one log handler',
            context: 'The Logger was constructed with an empty handler list, so every record would be dropped',
            suggestion: 'Pass at least one LogHandlerInterface, or build the logger through LoggerFactory from \'log.handlers\'',
        );
    }

    /**
     * @param list<string> $installed
     */
    private static function handlerList(
        array $installed,
    ): string {
        return $installed === [] ? 'none' : implode(', ', $installed);
    }
}
