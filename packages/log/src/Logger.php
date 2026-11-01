<?php

declare(strict_types=1);

namespace Marko\Log;

use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\Exceptions\LogWriteException;
use Psr\Clock\ClockInterface;

/**
 * Applies the level threshold, builds the record and sends it to every handler, in order.
 * The backend-specific write lives in the handlers (marko/log-file, marko/log-stream, ...).
 */
readonly class Logger implements LoggerInterface
{
    /**
     * @param list<LogHandlerInterface> $handlers
     * @throws InvalidLogConfigException When no handler is given
     */
    public function __construct(
        private array $handlers,
        private string $channel,
        private LogLevel $minimumLevel,
        private ClockInterface $clock,
    ) {
        if ($this->handlers === []) {
            throw InvalidLogConfigException::loggerWithoutHandlers();
        }
    }

    /**
     * @throws LogWriteException
     */
    public function emergency(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Emergency, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function alert(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Alert, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function critical(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Critical, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function error(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Error, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function warning(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Warning, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function notice(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Notice, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function info(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Info, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function debug(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Debug, $message, $context);
    }

    /**
     * @throws LogWriteException
     */
    public function log(
        LogLevel $level,
        string $message,
        array $context = [],
    ): void {
        if (!$level->meetsThreshold($this->minimumLevel)) {
            return;
        }

        $record = new LogRecord(
            level: $level,
            message: $message,
            context: $context,
            datetime: $this->clock->now(),
            channel: $this->channel,
        );

        foreach ($this->handlers as $handler) {
            $handler->handle($record);
        }
    }
}
