<?php

declare(strict_types=1);

namespace Marko\DevServer\Process;

use Marko\Core\Command\Output;
use Marko\DevServer\Exceptions\DevServerException;

class ProcessManager
{
    /** @var array<string, array{resource: resource, pipes: array<int, resource>}> */
    private array $processes = [];

    /** @var array<string, int> */
    private array $pids = [];

    public function __construct(
        private readonly Output $output,
    ) {}

    /**
     * Start a named process.
     *
     * @throws DevServerException If the process fails to start
     */
    public function start(
        string $name,
        string $command,
    ): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $wrappedCommand = $this->wrapWithNewProcessGroup($command);
        $process = proc_open($wrappedCommand, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw DevServerException::processFailedToStart($name, $command);
        }

        // Make stdout/stderr non-blocking
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $status = proc_get_status($process);
        $pid = $status['pid'];

        $this->processes[$name] = ['resource' => $process, 'pipes' => $pipes];
        $this->pids[$name] = $pid;

        // Wait briefly and check if the process exited immediately with an error
        usleep(150000); // 150ms — allows setsid wrapper to start and fail
        $status = proc_get_status($process);
        if (!$status['running'] && in_array($status['exitcode'], [126, 127], true)) {
            $this->stop($name);
            throw DevServerException::processFailedToStart($name, $command);
        }

        return $pid;
    }

    /**
     * Start a named process in the background, fully detached from PHP.
     *
     * Uses shell exec with output redirection instead of proc_open,
     * so the process survives PHP exit. Returns the PID.
     */
    public function startDetached(
        string $name,
        string $command,
    ): int {
        $wrappedCommand = $this->wrapWithNewProcessGroup($command);

        // Start fully detached: pipe stdin from tail to keep it open (some tools
        // like tailwind --watch exit when stdin closes), redirect output to
        // /dev/null, and background the process.
        $pid = (int) trim((string) shell_exec(
            "tail -f /dev/null | $wrappedCommand > /dev/null 2>&1 & echo $!",
        ));

        if ($pid <= 0) {
            throw DevServerException::processFailedToStart($name, $command);
        }

        // Brief check to see if process died immediately
        // (e.g. command not found, port in use)
        usleep(150000); // 150ms
        if (!$this->isDetachedRunning($pid)) {
            throw DevServerException::processFailedToStart($name, $command);
        }

        $this->pids[$name] = $pid;

        return $pid;
    }

    /**
     * Check if a detached process (or its process group) is still running.
     */
    private function isDetachedRunning(int $pid): bool
    {
        if (!function_exists('posix_kill')) {
            return false;
        }

        try {
            return @posix_kill(-$pid, 0) || posix_kill($pid, 0);
        } catch (\ValueError) {
            return false;
        }
    }

    /**
     * Stop a named process.
     */
    public function stop(string $name): void
    {
        if (!isset($this->processes[$name])) {
            return;
        }

        $process = $this->processes[$name]['resource'];
        $pipes = $this->processes[$name]['pipes'];

        // Close pipes
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_terminate($process);
        proc_close($process);

        unset($this->processes[$name], $this->pids[$name]);
    }

    /**
     * Stop all managed processes.
     */
    public function stopAll(): void
    {
        foreach (array_keys($this->processes) as $name) {
            $this->stop($name);
        }
    }

    /**
     * Get the PID of a named process.
     */
    public function getPid(string $name): ?int
    {
        return $this->pids[$name] ?? null;
    }

    /**
     * Get all PIDs indexed by name.
     *
     * @return array<string, int>
     */
    public function getPids(): array
    {
        return $this->pids;
    }

    /**
     * Check if a process is still running.
     */
    public function isRunning(string $name): bool
    {
        if (!isset($this->processes[$name])) {
            return false;
        }

        $status = proc_get_status($this->processes[$name]['resource']);

        return $status['running'];
    }

    /**
     * Run in foreground mode: stream process output with prefixes until all processes exit or a signal is received.
     *
     * Registers SIGINT/SIGTERM handlers for graceful shutdown when pcntl is available.
     */
    public function runForeground(): void
    {
        $signaled = false;

        if (function_exists('pcntl_signal')) {
            $handler = function () use (&$signaled): void {
                $signaled = true;
            };
            pcntl_signal(SIGINT, $handler);
            pcntl_signal(SIGTERM, $handler);
            pcntl_async_signals(true);
        }

        while (!$signaled && $this->processes !== []) {
            $this->drainOutput();

            // Remove exited processes and report their exit status
            foreach (array_keys($this->processes) as $name) {
                if (!$this->isRunning($name)) {
                    $this->drainOutput($name);
                    $exitCode = $this->getExitCode($name);
                    if ($exitCode !== 0) {
                        $this->writePrefix($name, "exited with code $exitCode");
                    } else {
                        $this->writePrefix($name, 'exited');
                    }
                    $this->stop($name);
                }
            }

            if ($this->processes !== []) {
                usleep(50000); // 50ms
            }
        }

        if ($signaled) {
            $this->stopAll();
        }
    }

    /**
     * Get the exit code of a process, or -1 if unknown.
     */
    private function getExitCode(string $name): int
    {
        if (!isset($this->processes[$name])) {
            return -1;
        }

        $status = proc_get_status($this->processes[$name]['resource']);

        return $status['exitcode'];
    }

    /**
     * Read available output from process pipes and write with prefix.
     */
    private function drainOutput(?string $onlyName = null): void
    {
        $names = $onlyName !== null ? [$onlyName] : array_keys($this->processes);

        foreach ($names as $name) {
            if (!isset($this->processes[$name])) {
                continue;
            }

            $pipes = $this->processes[$name]['pipes'];

            // Read stdout (pipe 1) and stderr (pipe 2)
            foreach ([1, 2] as $fd) {
                if (!is_resource($pipes[$fd])) {
                    continue;
                }

                while (($line = fgets($pipes[$fd])) !== false) {
                    $this->writePrefix($name, rtrim($line, "\r\n"));
                }
            }
        }
    }

    /**
     * Write a prefixed line to output.
     */
    public function writePrefix(
        string $name,
        string $line,
    ): void
    {
        $this->output->writeLine("[$name] $line");
    }

    /**
     * Wrap a command to run in its own process group.
     *
     * Uses PHP's posix_setsid() before exec'ing the command, so the process
     * becomes a session leader. This ensures all child processes (e.g. PHP
     * server workers, npx children) share the same group and can be killed
     * or status-checked together.
     */
    private function wrapWithNewProcessGroup(string $command): string
    {
        if (!function_exists('posix_setsid') || !function_exists('pcntl_exec')) {
            return "exec $command";
        }

        $encoded = base64_encode($command);
        $php = PHP_BINARY;

        // Use double quotes inside the PHP code to avoid escapeshellarg single-quote conflicts
        return "$php -r " . escapeshellarg(
            'posix_setsid();'
            . 'pcntl_exec("/bin/sh", ["-c", base64_decode("' . $encoded . '")]);',
        );
    }
}
