<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Adapters;

use Marko\Mcp\Exceptions\McpException;
use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;

/**
 * Reads the last N lines from the most recently modified .log file under a
 * given directory (defaults to {projectRoot}/storage/logs).
 *
 * The file is tailed: it is read backwards from the end in fixed-size chunks
 * until enough lines are found, so a multi-gigabyte log costs no more memory
 * than the lines returned (capped at MAX_BYTES).
 */
readonly class FileLogReader implements LogReaderInterface
{
    private const int CHUNK_BYTES = 8192;

    /**
     * Most bytes read from the end of the file, however few newlines they hold.
     */
    private const int MAX_BYTES = 1_048_576;

    public function __construct(
        private string $logsDir,
    ) {}

    /** @return list<string> */
    public function readLast(
        int $count,
    ): array {
        if (
            $count < 1
            || !is_dir($this->logsDir)
        ) {
            return [];
        }

        $logs = glob($this->logsDir . '/*.log') ?: [];

        if ($logs === []) {
            return [];
        }

        usort($logs, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $this->tail($logs[0], $count);
    }

    /**
     * @return list<string>
     * @throws McpException When the log file cannot be read
     */
    private function tail(
        string $path,
        int $count,
    ): array {
        $handle = is_readable($path) ? fopen($path, 'rb') : false;

        if ($handle === false) {
            throw McpException::internalError("Cannot read log file '$path'");
        }

        try {
            fseek($handle, 0, SEEK_END);
            $position = (int) ftell($handle);
            $buffer = '';
            $bytesRead = 0;

            // Read until the buffer holds more newlines than wanted lines, so the oldest line kept is complete.
            while (
                $position > 0
                && $bytesRead < self::MAX_BYTES
                && substr_count($buffer, "\n") <= $count
            ) {
                $length = min(self::CHUNK_BYTES, $position, self::MAX_BYTES - $bytesRead);
                $position -= $length;
                fseek($handle, $position);
                $buffer = (string) fread($handle, $length) . $buffer;
                $bytesRead += $length;
            }
        } finally {
            fclose($handle);
        }

        $lines = explode("\n", $buffer);

        if (end($lines) === '') {
            array_pop($lines);
        }

        // The first line is cut short when reading stopped before the start of the file; drop it unless it is all
        // there is.
        if (
            $position > 0
            && count($lines) > 1
        ) {
            array_shift($lines);
        }

        return array_map(
            fn (string $line): string => rtrim($line, "\r"),
            array_slice($lines, -$count),
        );
    }
}
