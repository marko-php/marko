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
    public function start(string $name, string $command): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

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
        usleep(50000); // 50ms
        $status = proc_get_status($process);
        if (!$status['running'] && $status['exitcode'] === 127) {
            $this->stop($name);
            throw DevServerException::processFailedToStart($name, $command);
        }

        return $pid;
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
     * Write a prefixed line to output.
     */
    public function writePrefix(string $name, string $line): void
    {
        $this->output->writeLine("[$name] $line");
    }
}
