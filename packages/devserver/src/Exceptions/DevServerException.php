<?php

declare(strict_types=1);

namespace Marko\DevServer\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\DevServer\Process\ServerHost;
use Throwable;

class DevServerException extends MarkoException
{
    public static function processFailedToStart(
        string $name,
        string $command,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Failed to start process '$name' with command: $command"
                . ($reason !== null ? " ($reason)" : ''),
            context: 'While starting development services',
            suggestion: "Check that the command exists and is executable. Run 'marko dev:status' to see current state.",
        );
    }

    public static function processFailedToStop(
        string $name,
        int $pid,
    ): self {
        return new self(
            message: "Failed to stop process '$name' (PID $pid): it was still running after SIGTERM and SIGKILL",
            context: 'While stopping development services',
            suggestion: "The process may be stuck in uninterruptible I/O. Inspect it with 'ps -o pid,stat,command -g $pid' and kill it manually with 'kill -9 -$pid'.",
        );
    }

    public static function serverNotReady(
        string $host,
        int $port,
        float $timeoutSeconds,
    ): self {
        $address = ServerHost::formatForUri($host) . ":$port";

        return new self(
            message: "PHP server did not accept connections on $address within $timeoutSeconds seconds",
            context: 'While starting PHP development server',
            suggestion: "Check the [php] output for errors, and that nothing is blocking connections to $address.",
        );
    }

    public static function portInUse(
        int $port,
    ): self {
        return new self(
            message: "Port $port is already in use",
            context: 'While starting PHP development server',
            suggestion: "Use a different port with --port=XXXX or stop the process using port $port.",
        );
    }

    public static function invalidHost(
        string $host,
    ): self {
        return new self(
            message: "Invalid host value: '$host'",
            context: 'While reading the --host option or the dev.host config value',
            suggestion: 'Use a hostname or an IP address, e.g. --host=localhost, --host=0.0.0.0, --host=::1 or --host=[::1]',
        );
    }

    /**
     * @param int|null $exitCode The server's exit code, or null when it is unknown
     * @param string $output What the server printed before it exited
     */
    public static function serverExited(
        string $host,
        int $port,
        ?int $exitCode,
        string $output,
    ): self {
        $address = ServerHost::formatForUri($host) . ":$port";
        $output = trim($output);

        return new self(
            message: 'PHP server exited' . ($exitCode !== null ? " with code $exitCode" : '')
                . " before accepting connections on $address",
            context: 'While starting PHP development server'
                . ($output !== '' ? ". Its output:\n$output" : ' (it printed no output)'),
            suggestion: 'Check the server output for the cause: an address that is not local to this machine, '
                . 'a syntax error in public/index.php, or a missing PHP extension.',
        );
    }

    /**
     * @param array<string, int> $survivors The processes still running, as name => PID
     */
    public static function rollbackFailed(
        Throwable $original,
        array $survivors,
    ): self {
        $named = implode(', ', array_map(
            static fn (string $name, int $pid): string => "'$name' (PID $pid)",
            array_map(strval(...), array_keys($survivors)),
            $survivors,
        ));
        $groups = implode(' ', array_map(static fn (int $pid): string => "-$pid", $survivors));

        return new self(
            message: "The development environment failed to start, and these services are still running: $named",
            context: 'While stopping the services started before the failure. The failure was: '
                . $original->getMessage(),
            suggestion: "Stop them manually with 'kill -9 $groups', then fix the failure and run 'marko up' again.",
            previous: $original,
        );
    }
}
