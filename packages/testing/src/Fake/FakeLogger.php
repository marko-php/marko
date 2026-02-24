<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;
use Marko\Testing\Exceptions\AssertionFailedException;

class FakeLogger implements LoggerInterface
{
    /** @var array<array{level: LogLevel, message: string, context: array<string, mixed>}> */
    public private(set) array $entries = [];

    public function emergency(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Emergency, $message, $context);
    }

    public function alert(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Alert, $message, $context);
    }

    public function critical(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Critical, $message, $context);
    }

    public function error(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Error, $message, $context);
    }

    public function warning(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Warning, $message, $context);
    }

    public function notice(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Notice, $message, $context);
    }

    public function info(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Info, $message, $context);
    }

    public function debug(
        string $message,
        array $context = [],
    ): void {
        $this->log(LogLevel::Debug, $message, $context);
    }

    public function log(
        LogLevel $level,
        string $message,
        array $context = [],
    ): void {
        $this->entries[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /** @return array<array{level: LogLevel, message: string, context: array<string, mixed>}> */
    public function entriesForLevel(
        LogLevel $level,
    ): array {
        return array_values(array_filter(
            $this->entries,
            fn (array $entry) => $entry['level'] === $level,
        ));
    }

    public function assertLogged(
        string $message,
        ?LogLevel $level = null,
    ): void {
        $found = array_any(
            $this->entries,
            fn (array $entry) => $entry['message'] === $message
                && ($level === null || $entry['level'] === $level),
        );

        if (!$found) {
            throw AssertionFailedException::expectedContains('log entries', $message);
        }
    }

    public function assertNothingLogged(): void
    {
        if ($this->entries !== []) {
            throw AssertionFailedException::expectedEmpty('log entries');
        }
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
