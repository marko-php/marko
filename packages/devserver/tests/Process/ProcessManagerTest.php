<?php

declare(strict_types=1);

use Marko\Core\Command\Output;
use Marko\DevServer\Exceptions\DevServerException;
use Marko\DevServer\Process\ProcessManager;

it('starts a process with proc_open', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $pid = $manager->start('echo', 'echo hello');

    expect($pid)->toBeInt()
        ->and($pid)->toBeGreaterThan(0);

    $manager->stopAll();
});

it('stops a running process', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('sleep', 'sleep 5');
    expect($manager->isRunning('sleep'))->toBeTrue();

    $manager->stop('sleep');

    expect($manager->getPid('sleep'))->toBeNull();
});

it('stops all managed processes', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('sleep1', 'sleep 5');
    $manager->start('sleep2', 'sleep 5');

    expect($manager->isRunning('sleep1'))->toBeTrue()
        ->and($manager->isRunning('sleep2'))->toBeTrue();

    $manager->stopAll();

    expect($manager->getPids())->toBeEmpty();
});

it('returns process PIDs after starting', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $pid1 = $manager->start('sleep1', 'sleep 5');
    $pid2 = $manager->start('sleep2', 'sleep 5');

    expect($manager->getPid('sleep1'))->toBe($pid1)
        ->and($manager->getPid('sleep2'))->toBe($pid2)
        ->and($manager->getPids())->toBe(['sleep1' => $pid1, 'sleep2' => $pid2]);

    $manager->stopAll();
});

it('detects when a process exits unexpectedly', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('echo', 'echo hello');

    expect(devserverWaitUntil(fn (): bool => !$manager->isRunning('echo')))->toBeTrue();

    $manager->stopAll();
});

it('throws DevServerException when process fails to start', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    // A generous probe window: start() returns as soon as the command fails, so this
    // costs nothing on a fast machine and survives a slow one under parallel load
    $manager = new ProcessManager($output, startProbeSeconds: 5.0);

    expect(fn () => $manager->start('bad', '/nonexistent-command-abc123'))
        ->toThrow(DevServerException::class);
});

it('detects a command that fails after the default probe window when given a longer one', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 5.0);

    // Exits with "command not found" well after the 0.5s default probe window
    expect(fn () => $manager->start('slow-fail', 'sleep 0.8; exit 127'))
        ->toThrow(DevServerException::class);
});

it('reports the exit code of a command that fails within the probe window', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 5.0);

    expect(fn () => $manager->start('slow-fail', 'sleep 0.3; exit 127'))
        ->toThrow(DevServerException::class, 'exited with code 127');
});

it('watches a running process for half a second by default', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $start = microtime(true);
    $manager->start('sleep', 'sleep 30');
    $elapsed = microtime(true) - $start;

    $manager->stopAll();

    expect($elapsed)->toBeGreaterThanOrEqual(0.5);
});

it('fails with processFailedToStart for a missing executable without relying on the probe window', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    // A zero probe window: only the pre-flight lookup can catch the missing executable
    $manager = new ProcessManager($output, startProbeSeconds: 0.0);

    expect(fn () => $manager->start('missing', 'nonexistent-binary-abc123 --watch'))
        ->toThrow(DevServerException::class, "Failed to start process 'missing'")
        ->and($manager->getPids())->toBeEmpty();
});

it('fails with processFailedToStart for a path that is not executable', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.0);
    $script = devserverMarkerPath();
    file_put_contents($script, "#!/bin/sh\nsleep 30\n");
    chmod($script, 0644);

    try {
        expect(fn () => $manager->start('not-executable', "$script --flag"))
            ->toThrow(DevServerException::class, 'is not executable');
    } finally {
        unlink($script);
    }
});

it('resolves the executable after leading environment assignments and env', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.0);

    expect(fn () => $manager->start('assigned', 'FOO=bar nonexistent-binary-abc123'))
        ->toThrow(DevServerException::class, "'nonexistent-binary-abc123' was not found")
        ->and(fn () => $manager->start('env', 'env PHP_CLI_SERVER_WORKERS=4 nonexistent-binary-abc123 -S'))
        ->toThrow(DevServerException::class, "'nonexistent-binary-abc123' was not found");

    $pid = $manager->start('env-ok', 'env FOO=bar sleep 30');

    expect($manager->isRunning('env-ok'))->toBeTrue()
        ->and($pid)->toBeGreaterThan(0);

    $manager->stopAll();
});

it('leaves shell syntax it cannot resolve to the runtime probe', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.0);

    // Builtins, reserved words, quoting and expansions are not resolved up front
    $manager->start('builtin', "trap '' TERM; sleep 30");
    $manager->start('reserved', 'if true; then sleep 30; fi');
    $manager->start('quoted', '"sleep" 30');
    $manager->start('expanded', '$(echo sleep) 30');

    expect($manager->getPids())->toHaveCount(4);

    $manager->stopAll();
});

it('names the reason a process failed to start in the exception', function (): void {
    $exception = DevServerException::processFailedToStart(
        'vite',
        'npx vite',
        "Executable 'npx' was not found in PATH",
    );

    expect($exception->getMessage())
        ->toBe("Failed to start process 'vite' with command: npx vite (Executable 'npx' was not found in PATH)")
        ->and(DevServerException::processFailedToStart('vite', 'npx vite')->getMessage())
        ->toBe("Failed to start process 'vite' with command: npx vite");
});

it('returns from start as soon as the process exits within the probe window', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 5.0);

    $start = microtime(true);
    $manager->start('echo', 'echo hello');

    expect(microtime(true) - $start)->toBeLessThan(2.5);

    $manager->stopAll();
});

it('prefixes output lines with process name', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->writePrefix('php', 'Server started on port 8000');

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[php] Server started on port 8000');
});

it('streams prefixed output in foreground mode until processes exit', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('echo', 'echo "hello from echo"');
    $manager->runForeground();

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[echo] hello from echo');
});

it('streams output from multiple processes in foreground mode', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('greet', 'echo "hi there"');
    $manager->start('count', 'echo "one two three"');
    $manager->runForeground();

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[greet] hi there')
        ->and($result)->toContain('[count] one two three');
});

it('returns when all foreground processes have exited', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('fast', 'echo done');

    // runForeground should return once the process exits
    $manager->runForeground();

    expect($manager->getPids())->toBeEmpty();
});

it('reports process exit with success status', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('task', 'echo done');
    $manager->runForeground();

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[task] exited');
});

it('reports process exit with failure status', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $manager->start('fail', 'sh -c "exit 1"');
    $manager->runForeground();

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[fail] exited with code 1');
});

it('captures the actual command PID not the shell wrapper PID', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $pid = $manager->start('sleep', 'sleep 10');

    // The stored PID must be the sleep process itself.
    // With exec prefix, posix_kill($pid, 0) returns true because the PID
    // is the actual long-running command, not a transient shell wrapper.
    expect(posix_kill($pid, 0))->toBeTrue();

    $manager->stop('sleep');
});

it('reports running processes as running in dev:status', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $pid = $manager->start('sleep', 'sleep 10');

    // Simulate what dev:status does: check the stored PID via posix_kill
    // The PID must still be alive after the start() call returns
    expect(posix_kill($pid, 0))->toBeTrue();

    $manager->stop('sleep');
});

it('reports stopped processes as stopped in dev:status', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $pid = $manager->start('sleep', 'sleep 10');
    $manager->stop('sleep');

    // stop() only returns once the process and its group are gone, so no wait is needed
    expect(posix_kill($pid, 0))->toBeFalse()
        ->and(devserverProcessGroupAlive($pid))->toBeFalse();
});

it('correctly tracks PID for long-running processes', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $pid = $manager->start('sleep', 'sleep 30');

    // Wait until the setsid wrapper has exec'd into its own process group
    expect(devserverWaitUntil(fn (): bool => devserverProcessGroupAlive($pid)))->toBeTrue()
        // The PID must still be the running process (not a dead shell wrapper)
        ->and(posix_kill($pid, 0))->toBeTrue()
        ->and($pid)->toBe($manager->getPid('sleep'));

    $manager->stop('sleep');
});

it('makes the reported PID the leader of its own process group', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    // Shells that fork instead of exec'ing a lone command (e.g. dash as /bin/sh) must not
    // leave a wrapper shell as the reported PID: stop() signals that PID's group
    $pid = $manager->start('sleep', 'sleep 30');

    expect(devserverWaitUntil(fn (): bool => posix_getpgid($pid) === $pid))->toBeTrue();

    $manager->stop('sleep');
});

it('leaves no process in the group once stop returns', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $pid = $manager->start('sleep', 'sleep 30');
    expect(devserverWaitUntil(fn (): bool => devserverProcessGroupAlive($pid)))->toBeTrue();

    $manager->stop('sleep');

    expect(devserverProcessGroupAlive($pid))->toBeFalse()
        ->and($manager->isRunning('sleep'))->toBeFalse();
});

it('stops child processes that share the process group', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.15);

    $marker = devserverMarkerPath();

    // The shell leader backgrounds a child in the same process group and records its PID
    $pid = $manager->start('tree', "sleep 30 & echo \$! > $marker; wait");
    expect(devserverWaitUntil(fn (): bool => devserverMarkerPid($marker) > 0))->toBeTrue();
    $childPid = devserverMarkerPid($marker);
    @unlink($marker);

    expect(posix_kill($childPid, 0))->toBeTrue();

    $manager->stop('tree');

    expect(devserverProcessGroupAlive($pid))->toBeFalse()
        ->and(posix_kill($childPid, 0))->toBeFalse();
});

it('escalates to SIGKILL when a process ignores SIGTERM', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, stopTimeoutSeconds: 0.5);
    $marker = devserverMarkerPath();

    // Ignore SIGTERM (inherited by the sleep child), then signal readiness via a marker file
    $pid = $manager->start('stubborn', "trap '' TERM; touch $marker; sleep 30");
    expect(devserverWaitUntil(fn (): bool => file_exists($marker)))->toBeTrue();

    $manager->stop('stubborn');
    @unlink($marker);

    expect(devserverProcessGroupAlive($pid))->toBeFalse()
        ->and(posix_kill($pid, 0))->toBeFalse();
});

it('returns within the stop timeout when a process ignores SIGTERM', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, stopTimeoutSeconds: 0.5);
    $marker = devserverMarkerPath();

    $manager->start('stubborn', "trap '' TERM; touch $marker; sleep 30");
    expect(devserverWaitUntil(fn (): bool => file_exists($marker)))->toBeTrue();

    $start = microtime(true);
    $manager->stop('stubborn');
    $elapsed = microtime(true) - $start;
    @unlink($marker);

    // 0.5s grace period plus a bounded wait for SIGKILL to land — never the 30s sleep
    expect($elapsed)->toBeGreaterThanOrEqual(0.5)
        ->and($elapsed)->toBeLessThan(5.0);
});

it('stops a process that has already exited without waiting', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, stopTimeoutSeconds: 5.0);

    $manager->start('echo', 'echo hello');
    expect(devserverWaitUntil(fn (): bool => !$manager->isRunning('echo')))->toBeTrue();

    $start = microtime(true);
    $manager->stop('echo');

    expect(microtime(true) - $start)->toBeLessThan(1.0)
        ->and($manager->getPid('echo'))->toBeNull();
});

it('overlaps the stop grace periods of all processes in stopAll', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, stopTimeoutSeconds: 1.0);
    $markerA = devserverMarkerPath();
    $markerB = devserverMarkerPath();

    $manager->start('a', "trap '' TERM; touch $markerA; sleep 30");
    $manager->start('b', "trap '' TERM; touch $markerB; sleep 30");
    expect(devserverWaitUntil(fn (): bool => file_exists($markerA) && file_exists($markerB)))->toBeTrue();

    $start = microtime(true);
    $manager->stopAll();
    $elapsed = microtime(true) - $start;
    @unlink($markerA);
    @unlink($markerB);

    // Stopping one after the other would take at least 2 x 1.0s
    expect($elapsed)->toBeLessThan(2.0)
        ->and($manager->getPids())->toBeEmpty();
});

it('creates a loud exception when a process cannot be stopped', function (): void {
    $exception = DevServerException::processFailedToStop('php', 12345);

    expect($exception->getMessage())->toContain("'php'")
        ->and($exception->getMessage())->toContain('12345')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->toContain('kill -9 -12345');
});

/**
 * A fresh, empty directory for the per-start status files of detached processes.
 */
function devserverStatusDirectory(): string
{
    $dir = devserverMarkerPath();
    mkdir($dir);

    return $dir;
}

/**
 * The `tail -f /dev/null` processes in the test runner's own process group.
 */
function devserverKeepAlivesInOwnGroup(): int
{
    $own = posix_getpgrp();
    $count = 0;

    foreach (explode("\n", (string) shell_exec('ps -axo pgid=,command=')) as $line) {
        if (preg_match('/\A\s*(\d+)\s+(.*)\z/', $line, $matches) === 1
            && (int) $matches[1] === $own
            && str_starts_with($matches[2], 'tail -f /dev/null')
        ) {
            $count++;
        }
    }

    return $count;
}

it('starts a detached process that keeps running after startDetached returns', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.2);

    $pid = $manager->startDetached('sleep', 'sleep 30');

    try {
        expect($pid)->toBeGreaterThan(0)
            ->and($manager->getPid('sleep'))->toBe($pid)
            ->and(devserverProcessGroupAlive($pid))->toBeTrue();
    } finally {
        devserverStopDetached($pid);
    }
});

it('returns a detached PID that already leads its process group, even with a zero probe window', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    // With no probe window, only waiting for the supervisor's posix_setsid() makes the group exist on return
    $manager = new ProcessManager($output, startProbeSeconds: 0.0);

    $pid = $manager->startDetached('sleep', 'sleep 30');

    try {
        expect(posix_getpgid($pid))->toBe($pid)
            ->and(devserverProcessGroupAlive($pid))->toBeTrue();
    } finally {
        devserverStopDetached($pid);
    }
});

it('makes the detached PID the leader of a process group containing the command', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.2);
    $marker = devserverMarkerPath();

    $pid = $manager->startDetached('tree', "sleep 30 & echo \$! > $marker; wait");

    try {
        expect(devserverWaitUntil(fn (): bool => devserverMarkerPid($marker) > 0))->toBeTrue();
        $childPid = devserverMarkerPid($marker);

        expect(posix_getpgid($pid))->toBe($pid)
            ->and(posix_getpgid($childPid))->toBe($pid);
    } finally {
        devserverStopDetached($pid);
        @unlink($marker);
    }

    expect(devserverProcessGroupAlive($pid))->toBeFalse();
});

it('keeps stdin open for a detached process until it exits', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.3);

    // cat exits as soon as its stdin reaches end-of-file, which startDetached() would report
    $pid = $manager->startDetached('reader', 'cat > /dev/null');

    devserverStopDetached($pid);

    expect(devserverProcessGroupAlive($pid))->toBeFalse();
});

it('reports a detached command that exits 127 after 300ms as processFailedToStart', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    // startDetached() returns as soon as the command exits, so a long window costs nothing here
    $manager = new ProcessManager($output, startProbeSeconds: 5.0);

    expect(fn () => $manager->startDetached('slow-fail', 'sleep 0.3; exit 127'))
        ->toThrow(
            DevServerException::class,
            "Failed to start process 'slow-fail' with command: sleep 0.3; exit 127 (exited with code 127)",
        )
        ->and($manager->getPid('slow-fail'))->toBeNull();
});

it(
    'reports a detached command that exits with a non-127 code inside the probe window as processFailedToStart',
    function (): void {
        $output = new Output(fopen('php://memory', 'r+'));
        $manager = new ProcessManager($output, startProbeSeconds: 5.0);

        expect(fn () => $manager->startDetached('busy-port', 'sleep 0.2; exit 1'))
            ->toThrow(DevServerException::class, 'exited with code 1');
    },
);

it('reports a missing detached executable as processFailedToStart', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output, startProbeSeconds: 0.0);

    expect(fn () => $manager->startDetached('missing', 'nonexistent-binary-abc123 --watch'))
        ->toThrow(DevServerException::class, "Executable 'nonexistent-binary-abc123' was not found in PATH");
});

it('leaves no keep-alive or status file behind once a detached process is stopped', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $statusDirectory = devserverStatusDirectory();
    $manager = new ProcessManager($output, startProbeSeconds: 0.2, statusDirectory: $statusDirectory);
    $keepAlivesBefore = devserverKeepAlivesInOwnGroup();

    $pid = $manager->startDetached('sleep', 'sleep 30');

    expect(glob("$statusDirectory/*"))->toBe([]);

    devserverStopDetached($pid);

    expect(devserverKeepAlivesInOwnGroup())->toBe($keepAlivesBefore);

    // A process that exits on its own after the probe window cannot write into the removed status dir
    $marker = devserverMarkerPath();
    $pid = $manager->startDetached('short', "touch $marker; sleep 0.5");

    try {
        expect(devserverWaitUntil(fn (): bool => !devserverProcessGroupAlive($pid)))->toBeTrue()
            ->and(glob("$statusDirectory/*"))->toBe([]);
    } finally {
        devserverStopDetached($pid);
        @unlink($marker);
        rmdir($statusDirectory);
    }
});

it('reports a port another process is listening on as unavailable', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')));
    [$server, $port] = devserverListen();

    try {
        expect($manager->isPortAvailable('127.0.0.1', $port))->toBeFalse()
            ->and($manager->isPortAvailable('localhost', $port))->toBeFalse();
    } finally {
        fclose($server);
    }
});

it('reports a port held on the wildcard address as unavailable when probing loopback', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')));
    [$server, $port] = devserverListen('0.0.0.0');

    try {
        expect($manager->isPortAvailable('127.0.0.1', $port))->toBeFalse()
            ->and($manager->isPortAvailable('0.0.0.0', $port))->toBeFalse();
    } finally {
        fclose($server);
    }
});

it('does not report an unbindable non-local host as a port in use', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')));

    // TEST-NET-1 (RFC 5737) is never assigned to a local interface, so binding fails without the port being taken
    expect($manager->isPortAvailable('192.0.2.1', devserverFreePort()))->toBeTrue();
});

it('reports a free port as available', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')));

    expect($manager->isPortAvailable('127.0.0.1', devserverFreePort()))->toBeTrue();
});

it('returns true as soon as the server accepts connections', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), serverReadyTimeoutSeconds: 10.0);
    $port = devserverFreePort();
    $docroot = devserverStatusDirectory();

    $manager->start('php', 'php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($docroot));

    try {
        $start = microtime(true);

        expect($manager->waitUntilAccepting('php', '127.0.0.1', $port))->toBeTrue()
            ->and(microtime(true) - $start)->toBeLessThan(5.0)
            ->and($manager->isRunning('php'))->toBeTrue();
    } finally {
        $manager->stopAll();
        rmdir($docroot);
    }
});

it('returns false when the process exits before accepting connections', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), serverReadyTimeoutSeconds: 10.0);
    $port = devserverFreePort();

    $manager->start('php', 'sleep 0.2; exit 1');

    $start = microtime(true);

    expect($manager->waitUntilAccepting('php', '127.0.0.1', $port))->toBeFalse()
        ->and(microtime(true) - $start)->toBeLessThan(5.0);

    $manager->stopAll();
});

it('throws when the server neither accepts connections nor exits before the timeout', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), serverReadyTimeoutSeconds: 0.3);
    $port = devserverFreePort();

    $manager->start('php', 'sleep 30');

    try {
        expect(fn () => $manager->waitUntilAccepting('php', '127.0.0.1', $port))
            ->toThrow(
                DevServerException::class,
                "PHP server did not accept connections on 127.0.0.1:$port within 0.3 seconds",
            );
    } finally {
        $manager->stopAll();
    }
});

it('stops a detached process group with stop', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), startProbeSeconds: 0.2);
    $marker = devserverMarkerPath();

    $pid = $manager->startDetached('tree', "sleep 30 & echo \$! > $marker; wait");

    try {
        expect(devserverWaitUntil(fn (): bool => devserverMarkerPid($marker) > 0))->toBeTrue();
        $childPid = devserverMarkerPid($marker);

        $manager->stop('tree');

        expect(devserverProcessGroupAlive($pid))->toBeFalse()
            ->and(posix_kill($childPid, 0))->toBeFalse()
            ->and($manager->getPid('tree'))->toBeNull();
    } finally {
        devserverStopDetached($pid);
        @unlink($marker);
    }
});

it('stops detached and attached processes together with stopAll', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), startProbeSeconds: 0.2);

    $attached = $manager->start('attached', 'sleep 30');
    $detached = $manager->startDetached('detached', 'sleep 30');

    try {
        $manager->stopAll();

        expect(devserverProcessGroupAlive($attached))->toBeFalse()
            ->and(devserverProcessGroupAlive($detached))->toBeFalse()
            ->and($manager->getPids())->toBe([]);
    } finally {
        devserverStopDetached($detached);
    }
});

it('escalates to SIGKILL for a detached process group that ignores SIGTERM', function (): void {
    $manager = new ProcessManager(
        new Output(fopen('php://memory', 'r+')),
        stopTimeoutSeconds: 0.5,
        startProbeSeconds: 0.2,
    );
    $marker = devserverMarkerPath();

    $pid = $manager->startDetached('stubborn', "trap '' TERM; echo \$\$ > $marker; sleep 30");

    try {
        expect(devserverWaitUntil(fn (): bool => devserverMarkerPid($marker) > 0))->toBeTrue();
        $shellPid = devserverMarkerPid($marker);

        $manager->stop('stubborn');

        expect(devserverProcessGroupAlive($pid))->toBeFalse()
            ->and(posix_kill($shellPid, 0))->toBeFalse();
    } finally {
        devserverStopDetached($pid);
        @unlink($marker);
    }
});

it('leaves no process from a detached command that fails during the probe window', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), startProbeSeconds: 5.0);
    $marker = devserverMarkerPath();

    // The command leaves a child behind in its process group, then fails
    expect(fn () => $manager->startDetached('leaky', "sleep 30 & echo \$! > $marker; sleep 0.2; exit 1"))
        ->toThrow(DevServerException::class, 'exited with code 1');

    $childPid = devserverMarkerPid($marker);
    @unlink($marker);

    expect($childPid)->toBeGreaterThan(0)
        ->and(posix_kill($childPid, 0))->toBeFalse()
        ->and($manager->getPid('leaky'))->toBeNull();
});

it('reports the exit code of an exited process and null while it runs', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), startProbeSeconds: 0.0);
    $marker = devserverMarkerPath();

    $manager->start('failing', "until [ -f $marker ]; do sleep 0.01; done; exit 3");

    try {
        expect($manager->getExitCode('failing'))->toBeNull();

        touch($marker);

        expect(devserverWaitUntil(fn (): bool => !$manager->isRunning('failing')))->toBeTrue()
            ->and($manager->getExitCode('failing'))->toBe(3)
            ->and($manager->getExitCode('unknown'))->toBeNull();
    } finally {
        $manager->stopAll();
        @unlink($marker);
    }
});

it('collects the unread output of a process', function (): void {
    $manager = new ProcessManager(new Output(fopen('php://memory', 'r+')), startProbeSeconds: 0.0);

    $manager->start('chatty', 'echo out; echo err >&2; exit 1');

    expect(devserverWaitUntil(fn (): bool => !$manager->isRunning('chatty')))->toBeTrue()
        ->and($manager->collectOutput('chatty'))->toBe("out\nerr")
        ->and($manager->collectOutput('unknown'))->toBe('');

    $manager->stopAll();
});
