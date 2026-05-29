<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Adapters;

use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;

/**
 * Reads the last N lines from the most recently modified .log file under a
 * given directory (defaults to {projectRoot}/storage/logs).
 */
readonly class FileLogReader implements LogReaderInterface
{
    public function __construct(
        private string $logsDir,
    ) {}

    /** @return list<string> */
    public function readLast(int $count): array
    {
        if (!is_dir($this->logsDir)) {
            return [];
        }

        $logs = glob($this->logsDir . '/*.log') ?: [];

        if ($logs === []) {
            return [];
        }

        usort($logs, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $latest = $logs[0];

        $contents = file($latest, FILE_IGNORE_NEW_LINES);

        if ($contents === false) {
            return [];
        }

        return array_slice($contents, -$count);
    }
}
