<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Adapters;

use Marko\Mcp\Tools\Runtime\Contracts\ErrorTrackerInterface;
use Throwable;

/**
 * Reads the most recent error from a JSON file. The file is opt-in: anything
 * implementing the host app's error pipeline can call FileErrorTracker::record()
 * to populate it, and the last_error MCP tool will read it from there.
 */
readonly class FileErrorTracker implements ErrorTrackerInterface
{
    /**
     * Persist a throwable to a JSON file at $errorFilePath. Safe to call from any
     * error handler — best-effort, never throws.
     */
    public static function record(
        string $errorFilePath,
        Throwable $throwable,
    ): void
    {
        $payload = [
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
            'timestamp' => time(),
        ];

        $dir = dirname($errorFilePath);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($errorFilePath, json_encode($payload, JSON_PRETTY_PRINT));
    }

    public function __construct(
        private string $errorFilePath,
    ) {}

    /** @return ?array{message: string, file: string, line: int, trace: string, timestamp: int} */
    public function lastError(): ?array
    {
        if (!is_file($this->errorFilePath)) {
            return null;
        }

        $raw = file_get_contents($this->errorFilePath);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (
            !is_array($data)
            || !isset($data['message'], $data['file'], $data['line'], $data['trace'], $data['timestamp'])
        ) {
            return null;
        }

        return [
            'message' => (string) $data['message'],
            'file' => (string) $data['file'],
            'line' => (int) $data['line'],
            'trace' => (string) $data['trace'],
            'timestamp' => (int) $data['timestamp'],
        ];
    }
}
