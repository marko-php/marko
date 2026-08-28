<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Support;

use Random\RandomException;
use RuntimeException;

/**
 * Spawns a real `rr serve` process, running the real `worker.php` shipped by
 * the package, against a fixture application — exactly what `rr:serve`
 * launches in production, except pointed at an ephemeral port so parallel
 * test runs cannot collide and forced to exactly one worker so every request
 * this test drives is guaranteed to land on the same PHP process.
 *
 * Nothing here fakes RoadRunner: the binary, the goridge wire protocol, and
 * the HTTP server are all real. Only the port and the worker count are
 * chosen for the test's benefit.
 */
class RoadRunnerServerProcess
{
    private const int READY_TIMEOUT_SECONDS = 15;

    private const int WORKER_COUNT = 1;

    private const string DEFAULT_HOST = '127.0.0.1';

    private const float READY_PROBE_TIMEOUT_SECONDS = 0.2;

    private const int READY_PROBE_INTERVAL_MICROSECONDS = 100_000;

    /** @var resource|null */
    private $process = null;

    private readonly string $host;

    private readonly int $port;

    private readonly string $configPath;

    private readonly string $stdoutPath;

    private readonly string $stderrPath;

    private readonly string $workDir;

    /**
     * @throws RandomException|RuntimeException
     */
    public function __construct(
        private readonly string $binary,
        private readonly string $basePath,
    ) {
        $this->host = self::DEFAULT_HOST;
        $this->port = self::findFreePort($this->host);

        $this->workDir = sys_get_temp_dir() . '/marko-roadrunner-e2e-' . bin2hex(random_bytes(8));
        mkdir($this->workDir, 0755, true);

        $this->configPath = $this->workDir . '/.rr.yaml';
        $this->stdoutPath = $this->workDir . '/stdout.log';
        $this->stderrPath = $this->workDir . '/stderr.log';

        file_put_contents($this->configPath, $this->renderConfig());
    }

    /**
     * @throws RuntimeException
     */
    public function start(): void
    {
        $process = proc_open(
            [$this->binary, 'serve', '-c', $this->configPath],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->stdoutPath, 'w'],
                2 => ['file', $this->stderrPath, 'w'],
            ],
            $pipes,
            $this->basePath,
        );

        if ($process === false) {
            throw new RuntimeException('Failed to start the rr serve process');
        }

        fclose($pipes[0]);
        $this->process = $process;

        $this->waitUntilReady();
    }

    public function stop(): void
    {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        $this->removeWorkDir();
    }

    public function client(): RoadRunnerHttpClient
    {
        return new RoadRunnerHttpClient($this->host, $this->port);
    }

    /**
     * @throws RuntimeException
     */
    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + self::READY_TIMEOUT_SECONDS;

        // A refused connection is the expected, repeated steady state while
        // rr is still starting up — silenced here rather than with '@' so
        // PHPUnit's own error handler (which does not honour '@') cannot
        // turn every poll attempt before the server is ready into a warning.
        set_error_handler(static fn (): bool => true);

        try {
            while (microtime(true) < $deadline) {
                $connection = fsockopen(
                    $this->host,
                    $this->port,
                    $errno,
                    $errstr,
                    self::READY_PROBE_TIMEOUT_SECONDS,
                );

                if ($connection !== false) {
                    fclose($connection);

                    return;
                }

                usleep(self::READY_PROBE_INTERVAL_MICROSECONDS);
            }
        } finally {
            restore_error_handler();
        }

        $stderr = is_file($this->stderrPath) ? file_get_contents($this->stderrPath) : '';
        $this->stop();

        throw new RuntimeException(
            "rr serve did not start listening on $this->host:$this->port within "
                . self::READY_TIMEOUT_SECONDS . " seconds.\nstderr:\n$stderr",
        );
    }

    private function renderConfig(): string
    {
        $host = $this->host;
        $port = $this->port;
        $basePath = $this->basePath;
        $workers = self::WORKER_COUNT;

        // An absolute path to both the PHP binary and the worker script:
        // rr resolves the command relative to wherever it was itself
        // launched from, which is not reliably this test's own cwd.
        $workerCommand = PHP_BINARY . ' ' . $basePath . '/vendor/marko/roadrunner/worker.php';

        return <<<YAML
        version: "3"

        server:
          command: "$workerCommand"
          relay: pipes
          env:
            MARKO_BASE_PATH: "$basePath"

        http:
          address: $host:$port
          pool:
            num_workers: $workers

        YAML;
    }

    /**
     * @throws RuntimeException
     */
    private static function findFreePort(
        string $host,
    ): int {
        $socket = stream_socket_server("tcp://$host:0", $errno, $errstr);

        if ($socket === false) {
            throw new RuntimeException("Unable to reserve a free TCP port: $errstr");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function removeWorkDir(): void
    {
        if (!is_dir($this->workDir)) {
            return;
        }

        // Explicit, not glob('*'): the config file is dotfile-named
        // (.rr.yaml) and glob's '*' does not match leading dots.
        foreach ([$this->configPath, $this->stdoutPath, $this->stderrPath] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->workDir);
    }
}
