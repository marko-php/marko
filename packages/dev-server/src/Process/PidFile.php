<?php

declare(strict_types=1);

namespace Marko\DevServer\Process;

use ValueError;

class PidFile
{
    private string $filePath;

    public function __construct(
        private readonly string $projectRoot,
    ) {
        $this->filePath = $projectRoot . '/.marko/dev.json';
    }

    /**
     * Write process entries to the PID file.
     *
     * @param array<ProcessEntry> $entries
     */
    public function write(array $entries): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = array_map(fn (ProcessEntry $e) => [
            'name' => $e->name,
            'pid' => $e->pid,
            'command' => $e->command,
            'port' => $e->port,
            'startedAt' => $e->startedAt,
        ], $entries);

        file_put_contents($this->filePath, json_encode(['processes' => $data], JSON_PRETTY_PRINT));
    }

    /**
     * Read process entries from the PID file.
     *
     * @return array<ProcessEntry>
     */
    public function read(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['processes'])) {
            return [];
        }

        return array_map(fn (array $p) => new ProcessEntry(
            name: $p['name'],
            pid: $p['pid'],
            command: $p['command'],
            port: $p['port'],
            startedAt: $p['startedAt'],
        ), $data['processes']);
    }

    /**
     * Remove the PID file.
     */
    public function clear(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    /**
     * Check if a process is still running.
     */
    public function isRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // Use posix_kill with signal 0 to check if process exists
        if (function_exists('posix_kill')) {
            try {
                return posix_kill($pid, 0);
            } catch (ValueError) {
                return false;
            }
        }

        // Fallback: check /proc on Linux
        return file_exists('/proc/' . $pid);
    }
}
