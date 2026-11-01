<?php

declare(strict_types=1);

namespace Marko\DevServer\Process;

use Marko\Core\Command\Output;
use Marko\DevServer\Exceptions\DevServerException;
use ValueError;

class ProcessManager
{
    private const int SIGTERM = 15;

    private const int SIGKILL = 9;

    /** How long to wait for SIGKILL to take effect before failing loudly. */
    private const float KILL_TIMEOUT_SECONDS = 2.0;

    /** How long past the probe window to wait for a detached supervisor to lead its process group before failing loudly. */
    private const float GROUP_LEADER_TIMEOUT_SECONDS = 10.0;

    private const int POLL_INTERVAL_MICROS = 10_000;

    /** Words the shell treats as syntax rather than a command to look up. */
    private const array SHELL_RESERVED_WORDS = [
        '!', '{', '}', '[[', ']]', 'case', 'do', 'done', 'elif', 'else', 'esac',
        'fi', 'for', 'function', 'if', 'in', 'select', 'then', 'time', 'until', 'while',
    ];

    /** @var array<string, array{resource: resource, pipes: array<int, resource>}> */
    private array $processes = [];

    /** @var array<string, int> Every running process this manager started, in start order */
    private array $pids = [];

    /** @var array<string, int> Processes started with startDetached(), which have no proc_open() resource */
    private array $detached = [];

    /**
     * @param float $stopTimeoutSeconds Grace period stop() gives a process group to exit after SIGTERM before sending SIGKILL
     * @param float $startProbeSeconds How long start() and startDetached() watch a new process for an early exit
     * @param string|null $statusDirectory Where startDetached() keeps the short-lived exit status files (default: the system temp dir)
     * @param float $serverReadyTimeoutSeconds How long waitUntilAccepting() waits for a server to accept connections
     */
    public function __construct(
        private readonly Output $output,
        private readonly float $stopTimeoutSeconds = 3.0,
        private readonly float $startProbeSeconds = 0.5,
        private readonly ?string $statusDirectory = null,
        private readonly float $serverReadyTimeoutSeconds = 10.0,
    ) {}

    /**
     * Check whether a server could listen on the given host and port.
     *
     * A port is in use when something already accepts connections on it, or when
     * binding it fails with "address already in use". Any other bind failure (such
     * as a host that is not a local address) is left for the server to report.
     */
    public function isPortAvailable(
        string $host,
        int $port,
    ): bool {
        if ($this->accepts($host, $port, 0.2)) {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $address = 'tcp://' . ServerHost::formatForUri($host) . ":$port";
        $server = $this->withoutWarnings(
            function () use ($address, &$errno, &$errstr): mixed {
                return stream_socket_server($address, $errno, $errstr);
            },
        );

        if (is_resource($server)) {
            fclose($server);

            return true;
        }

        $addressInUse = defined('SOCKET_EADDRINUSE') ? SOCKET_EADDRINUSE : null;

        return !($errno === $addressInUse || stripos($errstr, 'address already in use') !== false);
    }

    /**
     * Wait until a started server accepts connections on the given host and port.
     *
     * @return bool True once a connection is accepted, false if the process exited first
     * @throws DevServerException If the server neither accepts connections nor exits before the timeout
     */
    public function waitUntilAccepting(
        string $name,
        string $host,
        int $port,
    ): bool {
        $deadline = microtime(true) + $this->serverReadyTimeoutSeconds;

        while (true) {
            if (!$this->isRunning($name)) {
                return false;
            }

            if ($this->accepts($host, $port, min(0.1, max(0.01, $deadline - microtime(true))))) {
                return true;
            }

            if (microtime(true) >= $deadline) {
                throw DevServerException::serverNotReady($host, $port, $this->serverReadyTimeoutSeconds);
            }

            usleep(self::POLL_INTERVAL_MICROS);
        }
    }

    /**
     * Whether something accepts a TCP connection on the host and port.
     *
     * Wildcard addresses are probed through loopback, since nothing can connect to them directly.
     */
    private function accepts(
        string $host,
        int $port,
        float $timeoutSeconds,
    ): bool {
        $connectHost = match (trim($host, '[]')) {
            '0.0.0.0' => '127.0.0.1',
            '::' => '::1',
            default => $host,
        };

        $connection = $this->withoutWarnings(fn (): mixed => stream_socket_client(
            'tcp://' . ServerHost::formatForUri($connectHost) . ":$port",
            timeout: $timeoutSeconds,
        ));

        if (!is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }

    /**
     * Run an operation whose failure is an expected answer, not an error worth a warning.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function withoutWarnings(callable $operation): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Start a named process.
     *
     * The command's executable is looked up before anything is spawned, so a missing
     * or non-executable command fails however slow the machine is. The process is then
     * watched for the probe window and fails if it exits with code 126 or 127.
     *
     * @throws DevServerException If the process fails to start
     */
    public function start(
        string $name,
        string $command,
    ): int {
        $this->assertExecutable($name, $command);

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

        // Watch the process for the probe window, returning early once it exits,
        // and fail if it exited because the command was not found or not executable
        $deadline = microtime(true) + $this->startProbeSeconds;
        while (($status = proc_get_status($process))['running'] && microtime(true) < $deadline) {
            usleep(self::POLL_INTERVAL_MICROS);
        }

        if (!$status['running'] && in_array($status['exitcode'], [126, 127], true)) {
            $this->stopAfterFailedStart($name);
            throw DevServerException::processFailedToStart($name, $command, "exited with code {$status['exitcode']}");
        }

        return $pid;
    }

    /**
     * Fail before spawning when the command's executable is missing or not executable.
     *
     * Only a plain leading word is checked: anything the shell has to interpret first
     * (builtins and reserved words resolve fine; quoting and expansions are skipped) is
     * left to the probe window.
     *
     * @throws DevServerException If the executable does not exist or cannot be executed
     */
    private function assertExecutable(
        string $name,
        string $command,
    ): void {
        $executable = $this->leadingExecutable($command);

        if ($executable === null) {
            return;
        }

        if (str_contains($executable, '/')) {
            clearstatcache(true, $executable);

            if (!file_exists($executable)) {
                throw DevServerException::processFailedToStart($name, $command, "'$executable' does not exist");
            }

            if (!is_file($executable) || !is_executable($executable)) {
                throw DevServerException::processFailedToStart($name, $command, "'$executable' is not executable");
            }

            return;
        }

        exec('command -v ' . escapeshellarg($executable) . ' 2>/dev/null', $lookup, $exitCode);

        if ($exitCode !== 0) {
            throw DevServerException::processFailedToStart(
                $name,
                $command,
                "Executable '$executable' was not found in PATH",
            );
        }
    }

    /**
     * The executable a command runs, skipping leading variable assignments, `exec` and `env`.
     *
     * Returns null when the shell would have to interpret the word first (quotes,
     * expansions, operators, reserved words), because only the shell can resolve it.
     */
    private function leadingExecutable(string $command): ?string
    {
        $words = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words === false ? [] : $words as $word) {
            if ($word === 'exec' || $word === 'env') {
                continue;
            }

            if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*=[A-Za-z0-9_.\/:,@%+=-]*\z/', $word) === 1) {
                continue;
            }

            if (preg_match('/\A[A-Za-z0-9_.\/+@%:,][A-Za-z0-9_.\/+@%:,-]*\z/', $word) !== 1
                || in_array($word, self::SHELL_RESERVED_WORDS, true)
            ) {
                return null;
            }

            return $word;
        }

        return null;
    }

    /**
     * Start a named process in the background, fully detached from PHP.
     *
     * The command runs under a small PHP supervisor that becomes the leader of a new
     * session, so the process survives PHP exit and the returned PID leads its process
     * group. The supervisor holds the command's stdin open (some tools, like
     * tailwind --watch, exit when stdin closes), waits for it, and records its exit
     * status. Any exit inside the probe window fails with the command's exit code.
     *
     * @throws DevServerException If the process fails to start or exits during the probe window
     */
    public function startDetached(
        string $name,
        string $command,
    ): int {
        if (!function_exists('posix_setsid') || !function_exists('posix_kill') || !function_exists('pcntl_waitpid')) {
            throw new DevServerException(
                message: "Cannot start process '$name' in detached mode: the posix and pcntl extensions are required",
                context: 'While starting development services in detached mode',
                suggestion: "Enable ext-posix and ext-pcntl, or run 'marko up --foreground'.",
            );
        }

        $this->assertExecutable($name, $command);

        $statusDirectory = $this->createStatusDirectory();
        $statusFile = $statusDirectory . '/status';

        try {
            $pid = (int) trim((string) shell_exec(
                $this->supervisorCommand($command, $statusFile) . ' < /dev/null > /dev/null 2>&1 & echo $!',
            ));

            if ($pid <= 0) {
                throw DevServerException::processFailedToStart($name, $command);
            }

            $failure = $this->probeDetached($pid, $statusFile);
        } finally {
            $this->removeStatusDirectory($statusDirectory, $statusFile);
        }

        $this->detached[$name] = $pid;
        $this->pids[$name] = $pid;

        if ($failure !== null) {
            $this->stopAfterFailedStart($name);
            throw DevServerException::processFailedToStart($name, $command, $failure);
        }

        return $pid;
    }

    /**
     * Watch a detached process group for the probe window.
     *
     * @return string|null Why the process failed to start, or null when it is still running
     */
    private function probeDetached(
        int $pid,
        string $statusFile,
    ): ?string {
        $deadline = microtime(true) + $this->startProbeSeconds;

        while (true) {
            $exitCode = $this->readExitStatus($statusFile);

            if ($exitCode !== null && $exitCode !== 0) {
                return "exited with code $exitCode";
            }

            if (!$this->isDetachedRunning($pid)) {
                // The supervisor writes the status just before it exits, so read it once more
                $exitCode = $this->readExitStatus($statusFile);

                return $exitCode !== null ? "exited with code $exitCode" : 'exited during startup';
            }

            if (microtime(true) >= $deadline) {
                // The PID only leads its group once the supervisor's posix_setsid() ran, which a
                // loaded machine can delay past the probe window: wait for it, so callers that
                // signal or check the group (stop(), PidFile) never miss it
                if ($this->leadsProcessGroup($pid)) {
                    return null;
                }

                if (microtime(true) >= $deadline + self::GROUP_LEADER_TIMEOUT_SECONDS) {
                    return sprintf(
                        'did not become a process group leader within %s seconds',
                        self::GROUP_LEADER_TIMEOUT_SECONDS,
                    );
                }
            }

            usleep(self::POLL_INTERVAL_MICROS);
        }
    }

    /**
     * Whether the PID is the leader of its own process group.
     */
    private function leadsProcessGroup(int $pid): bool
    {
        return @posix_getpgid($pid) === $pid;
    }

    /**
     * The exit code the supervisor recorded, or null while the command is still running.
     */
    private function readExitStatus(string $statusFile): ?int
    {
        clearstatcache(true, $statusFile);

        if (!is_file($statusFile)) {
            return null;
        }

        $status = trim((string) file_get_contents($statusFile));

        return $status === '' ? null : (int) $status;
    }

    private function createStatusDirectory(): string
    {
        $directory = ($this->statusDirectory ?? sys_get_temp_dir())
            . '/marko-devserver-' . bin2hex(random_bytes(8));

        if (!$this->withoutWarnings(fn (): bool => mkdir($directory, 0700, true)) && !is_dir($directory)) {
            throw new DevServerException(
                message: "Cannot create the status directory $directory",
                context: 'While starting development services in detached mode',
                suggestion: 'Check that the system temp directory exists and is writable.',
            );
        }

        return $directory;
    }

    /**
     * Remove a status directory once the probe window is over.
     *
     * Without the directory, a status the supervisor writes later fails silently
     * instead of leaving a file behind.
     */
    private function removeStatusDirectory(
        string $directory,
        string $statusFile,
    ): void {
        // A second pass covers a status written between removing the file and removing the directory
        for ($attempt = 0; $attempt < 2 && is_dir($directory); $attempt++) {
            $this->withoutWarnings(fn (): bool => unlink($statusFile));
            $this->withoutWarnings(fn (): bool => rmdir($directory));
            clearstatcache(true, $directory);
        }
    }

    /**
     * Build the shell command that starts the detached supervisor.
     *
     * The supervisor calls posix_setsid() so its PID leads a new session and process
     * group, runs the command through /bin/sh with a stdin pipe it never writes to or
     * closes, waits for the command, writes its exit status, and exits with it.
     */
    private function supervisorCommand(
        string $command,
        string $statusFile,
    ): string {
        // Use double quotes inside the PHP code to avoid escapeshellarg single-quote conflicts
        $code = 'posix_setsid();'
            . '$process = proc_open(["/bin/sh", "-c", base64_decode("' . base64_encode($command) . '")],'
            . ' [0 => ["pipe", "r"], 1 => ["file", "/dev/null", "w"], 2 => ["file", "/dev/null", "w"]], $pipes);'
            . 'if (!is_resource($process)) { exit(126); }'
            . 'pcntl_waitpid(proc_get_status($process)["pid"], $status);'
            . '$code = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 128 + (int) pcntl_wtermsig($status);'
            . '@file_put_contents(base64_decode("' . base64_encode($statusFile) . '"), (string) $code);'
            . 'exit($code);';

        return 'exec ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code);
    }

    /**
     * Check if a detached process (or its process group) is still running.
     */
    private function isDetachedRunning(int $pid): bool
    {
        try {
            return @posix_kill(-$pid, 0) || @posix_kill($pid, 0);
        } catch (ValueError) {
            return false;
        }
    }

    /**
     * Stop a named process and every process in its process group.
     *
     * Sends SIGTERM to the process and its group, waits up to the stop timeout for
     * all of them to exit, then escalates to SIGKILL. When this returns, the
     * process and its group are gone.
     *
     * Works the same for processes started with start() and with startDetached().
     *
     * @throws DevServerException If the process group survives SIGKILL
     */
    public function stop(string $name): void
    {
        if (!isset($this->pids[$name])) {
            return;
        }

        $this->terminate([$name]);
    }

    /**
     * Stop a process whose start just failed, without letting a stop failure hide why it failed.
     *
     * A group that survives stays tracked, so a later stop() or stopAll() reports it.
     */
    private function stopAfterFailedStart(string $name): void
    {
        try {
            $this->stop($name);
        } catch (DevServerException) {
            // The start failure is the error to report; the survivor is still tracked
        }
    }

    /**
     * Terminate the named processes and their process groups, then release them.
     *
     * All groups share one SIGTERM grace period, so stopping several services
     * takes as long as the slowest one rather than the sum of all of them.
     *
     * @param list<string> $names
     * @throws DevServerException If a process group survives SIGKILL
     */
    private function terminate(array $names): void
    {
        $remaining = $this->waitForExit($names, 0.0);
        $this->signalAll($remaining, self::SIGTERM);

        $remaining = $this->waitForExit($remaining, $this->stopTimeoutSeconds);
        $this->signalAll($remaining, self::SIGKILL);

        $remaining = $this->waitForExit($remaining, self::KILL_TIMEOUT_SECONDS);

        foreach ($names as $name) {
            if (in_array($name, $remaining, true)) {
                continue;
            }

            // Close pipes only once the group is gone, so nothing gets SIGPIPE while shutting down
            if (isset($this->processes[$name])) {
                foreach ($this->processes[$name]['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }

                proc_close($this->processes[$name]['resource']);
            }

            unset($this->processes[$name], $this->detached[$name], $this->pids[$name]);
        }

        if ($remaining !== []) {
            throw DevServerException::processFailedToStop($remaining[0], $this->pids[$remaining[0]]);
        }
    }

    /**
     * @param list<string> $names
     */
    private function signalAll(
        array $names,
        int $signal,
    ): void {
        foreach ($names as $name) {
            $this->signal($name, $signal);
        }
    }

    /**
     * Send a signal to a process and to its process group.
     *
     * Both are signalled because the process may not have become a group
     * leader yet (it is killed before posix_setsid() runs), and because
     * children in the group outlive a leader that exits on its own.
     */
    private function signal(
        string $name,
        int $signal,
    ): void {
        $pid = $this->pids[$name];

        if (!isset($this->processes[$name])) {
            // A detached supervisor leads its own group once posix_setsid() ran; before that, only its PID reaches it
            try {
                @posix_kill(-$pid, $signal) || @posix_kill($pid, $signal);
            } catch (ValueError) {
                // The process is already gone
            }

            return;
        }

        if (function_exists('posix_kill')) {
            @posix_kill(-$pid, $signal);
        }

        $process = $this->processes[$name]['resource'];

        // Once reaped, the PID may be reused by an unrelated process — only signal a live child
        if (proc_get_status($process)['running']) {
            proc_terminate($process, $signal);
        }
    }

    /**
     * Whether a process or any process in its group is still alive.
     */
    private function isAlive(string $name): bool
    {
        if (!isset($this->processes[$name])) {
            return $this->isDetachedRunning($this->pids[$name]);
        }

        // proc_get_status() reaps the exited process, so a zombie never counts as running
        return proc_get_status($this->processes[$name]['resource'])['running']
            || $this->isProcessGroupAlive($this->pids[$name]);
    }

    /**
     * Poll until every named process has exited and its process group is empty, or the timeout passes.
     *
     * @param list<string> $names
     * @return list<string> The names still alive when the timeout passed
     */
    private function waitForExit(
        array $names,
        float $timeoutSeconds,
    ): array {
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $names = array_values(array_filter($names, $this->isAlive(...)));

            if ($names === [] || microtime(true) >= $deadline) {
                return $names;
            }

            usleep(self::POLL_INTERVAL_MICROS);
        }
    }

    /**
     * Check if any process in the process group led by the given PID is still alive.
     */
    private function isProcessGroupAlive(int $pgid): bool
    {
        if (!function_exists('posix_kill')) {
            return false;
        }

        return @posix_kill(-$pgid, 0);
    }

    /**
     * Stop all managed processes.
     *
     * Covers processes started with start() and with startDetached(). Every process
     * group gets SIGTERM up front, newest first, so their grace periods overlap
     * instead of adding up one service at a time. Processes that are gone are
     * released even when another one survives; the survivors stay in getPids().
     *
     * @throws DevServerException If a process group survives SIGKILL
     */
    public function stopAll(): void
    {
        if ($this->pids === []) {
            return;
        }

        // PHP turns numeric-string array keys into ints, so cast names back to strings
        $this->terminate(array_reverse(array_map(strval(...), array_keys($this->pids))));
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
                    $exitCode = $this->getExitCode($name) ?? -1;
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
     * Get the exit code of a process started with start().
     *
     * @return int|null The exit code, or null while the process runs or when it is not tracked
     */
    public function getExitCode(string $name): ?int
    {
        if (!isset($this->processes[$name])) {
            return null;
        }

        $status = proc_get_status($this->processes[$name]['resource']);

        return $status['running'] || $status['exitcode'] === -1 ? null : $status['exitcode'];
    }

    /**
     * Read the output of a process started with start() that has not been streamed yet.
     *
     * Returns stdout followed by stderr, trimmed. Call it before stop(), which closes the pipes.
     */
    public function collectOutput(string $name): string
    {
        if (!isset($this->processes[$name])) {
            return '';
        }

        $output = '';

        foreach ([1, 2] as $fd) {
            $pipe = $this->processes[$name]['pipes'][$fd];

            if (is_resource($pipe)) {
                $output .= (string) stream_get_contents($pipe);
            }
        }

        return trim($output);
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
    ): void {
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

        // `exec` makes the wrapper replace the spawning shell, so the PID proc_open reports
        // is the session leader. Shells such as dash fork a lone command instead of
        // exec'ing it, which would leave a short-lived shell as the reported PID.
        // Use double quotes inside the PHP code to avoid escapeshellarg single-quote conflicts
        return "exec $php -r " . escapeshellarg(
            'posix_setsid();'
            . 'pcntl_exec("/bin/sh", ["-c", base64_decode("' . $encoded . '")]);',
        );
    }
}
