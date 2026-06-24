<?php

declare(strict_types=1);

namespace Marko\DevAi\Process;

class CommandRunner implements CommandRunnerInterface
{
    /**
     * @param list<string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(
        string $command,
        array $args = [],
    ): array {
        $cmd = escapeshellcmd($command) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        // Detach stdin (read from /dev/null) so a child can never block waiting on
        // terminal input. Without this the child inherits the parent's TTY and any
        // unexpected prompt — e.g. composer's allow-plugins trust question — deadlocks
        // forever, with the prompt hidden because we buffer the child's output.
        $proc = proc_open(
            $cmd,
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($proc)) {
            return ['exitCode' => -1, 'stdout' => '', 'stderr' => 'proc_open failed'];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $open = [$pipes[1], $pipes[2]];

        while ($open !== []) {
            $read = $open;
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 1) === false) {
                break;
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if ($chunk === false || ($chunk === '' && feof($stream))) {
                    $open = array_values(array_filter($open, fn ($s) => $s !== $stream));

                    continue;
                }
                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    public function isOnPath(string $binary): bool
    {
        $result = $this->run('command', ['-v', $binary]);

        return $result['exitCode'] === 0 && trim($result['stdout']) !== '';
    }
}
