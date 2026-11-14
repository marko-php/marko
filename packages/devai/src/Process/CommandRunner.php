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
        // Pass the command as an argv array so proc_open execs it directly, with no
        // shell in between. Each element reaches the child verbatim, so a binary path
        // containing spaces (e.g. "/Users/x/My Apps/app/vendor/bin/marko") stays one
        // argument instead of splitting into a different executable plus arguments.
        $cmd = [$command, ...array_values($args)];
        // Detach stdin (read from /dev/null) so a child can never block waiting on
        // terminal input. Without this the child inherits the parent's TTY and any
        // unexpected prompt — e.g. composer's allow-plugins trust question — deadlocks
        // forever, with the prompt hidden because we buffer the child's output.
        //
        // Without a shell, a missing executable makes proc_open emit a warning and
        // return false instead of the child exiting 127. Capture that warning and turn
        // it into the result shape callers already handle (non-zero exit + stderr).
        $spawnError = null;
        set_error_handler(static function (int $errno, string $message) use (&$spawnError): bool {
            $spawnError = $message;

            return true;
        });

        try {
            $proc = proc_open(
                $cmd,
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
        } finally {
            restore_error_handler();
        }

        if (!is_resource($proc)) {
            return [
                'exitCode' => 127,
                'stdout' => '',
                'stderr' => "Could not start `$command`: " . ($spawnError ?? 'proc_open failed'),
            ];
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
                if (
                    $chunk === false
                    || (
                        $chunk === ''
                        && feof($stream)
                    )
                ) {
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

    public function isOnPath(
        string $binary,
    ): bool {
        // `command` is a shell builtin, so it has to run inside sh. The binary name is
        // passed as a positional parameter ($1), never interpolated into the script.
        $result = $this->run('sh', ['-c', 'command -v "$1"', 'sh', $binary]);

        return $result['exitCode'] === 0
            && trim($result['stdout']) !== '';
    }
}
