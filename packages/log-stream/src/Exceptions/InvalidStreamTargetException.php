<?php

declare(strict_types=1);

namespace Marko\Log\Stream\Exceptions;

use Marko\Log\Exceptions\InvalidLogConfigException;

class InvalidStreamTargetException extends InvalidLogConfigException
{
    /**
     * @param list<string> $supported
     */
    public static function unsupported(
        string $target,
        array $supported,
    ): self {
        $list = implode(' or ', $supported);

        return new self(
            message: "Unsupported log stream target '$target'",
            context: "Configured 'log.stream.target': '$target'",
            suggestion: "Set LOG_STREAM_TARGET (or 'log.stream.target') to $list. To write log files, use the 'file' handler from marko/log-file",
        );
    }

    public static function stdoutUnderRoadRunner(): self
    {
        return new self(
            message: 'Log stream target php://stdout is not allowed under RoadRunner',
            context: 'RR_MODE is set, so this process is a RoadRunner worker, and RoadRunner carries its worker protocol on stdout',
            suggestion: 'Set LOG_STREAM_TARGET=php://stderr; RoadRunner collects worker stderr into its own logs',
        );
    }
}
