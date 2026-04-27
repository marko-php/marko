<?php

declare(strict_types=1);

namespace Marko\Debugbar\Plugins;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Plugin;
use Marko\Debugbar\Debugbar;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;

#[Plugin(target: LoggerInterface::class)]
class LoggerPlugin
{
    public function __construct(
        private readonly Debugbar $debugbar,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'log')]
    public function afterLog(
        mixed $result,
        LogLevel $level,
        string $message,
        array $context = [],
    ): mixed
    {
        $this->debugbar->recordLog($level->value, $message, $context);

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'emergency')]
    public function afterEmergency(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'emergency', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'alert')]
    public function afterAlert(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'alert', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'critical')]
    public function afterCritical(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'critical', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'error')]
    public function afterError(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'warning')]
    public function afterWarning(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'notice')]
    public function afterNotice(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'notice', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'info')]
    public function afterInfo(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[After(method: 'debug')]
    public function afterDebug(
        mixed $result,
        string $message,
        array $context = [],
    ): mixed
    {
        return $this->record($result, 'debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(
        mixed $result,
        string $level,
        string $message,
        array $context,
    ): mixed
    {
        $this->debugbar->recordLog($level, $message, $context);

        return $result;
    }
}
