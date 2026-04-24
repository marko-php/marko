<?php

declare(strict_types=1);

namespace Marko\DevAi\Process;

class CommandRunner implements CommandRunnerInterface
{
    /**
     * @param list<string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(string $command, array $args = []): array
    {
        $cmd = escapeshellcmd($command) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($proc)) {
            return ['exitCode' => -1, 'stdout' => '', 'stderr' => 'proc_open failed'];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return ['exitCode' => $exitCode, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }

    public function isOnPath(string $binary): bool
    {
        $result = $this->run('command', ['-v', $binary]);

        return $result['exitCode'] === 0 && trim($result['stdout']) !== '';
    }
}
