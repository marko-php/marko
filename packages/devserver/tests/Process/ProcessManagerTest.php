<?php

declare(strict_types=1);

use Marko\Core\Command\Output;
use Marko\DevServer\Exceptions\DevServerException;
use Marko\DevServer\Process\ProcessManager;

it('starts a process with proc_open', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $pid = $manager->start('echo', 'echo hello');

    expect($pid)->toBeInt()
        ->and($pid)->toBeGreaterThan(0);

    $manager->stopAll();
});

it('stops a running process', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $manager->start('sleep', 'sleep 5');
    expect($manager->isRunning('sleep'))->toBeTrue();

    $manager->stop('sleep');

    expect($manager->getPid('sleep'))->toBeNull();
});

it('stops all managed processes', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $manager->start('sleep1', 'sleep 5');
    $manager->start('sleep2', 'sleep 5');

    expect($manager->isRunning('sleep1'))->toBeTrue()
        ->and($manager->isRunning('sleep2'))->toBeTrue();

    $manager->stopAll();

    expect($manager->getPids())->toBeEmpty();
});

it('returns process PIDs after starting', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $pid1 = $manager->start('sleep1', 'sleep 5');
    $pid2 = $manager->start('sleep2', 'sleep 5');

    expect($manager->getPid('sleep1'))->toBe($pid1)
        ->and($manager->getPid('sleep2'))->toBe($pid2)
        ->and($manager->getPids())->toBe(['sleep1' => $pid1, 'sleep2' => $pid2]);

    $manager->stopAll();
});

it('detects when a process exits unexpectedly', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $manager->start('echo', 'echo hello');

    // Give the short-lived process time to complete
    usleep(100000); // 100ms

    expect($manager->isRunning('echo'))->toBeFalse();

    $manager->stopAll();
});

it('throws DevServerException when process fails to start', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    expect(fn () => $manager->start('bad', '/nonexistent-command-abc123'))
        ->toThrow(DevServerException::class);
});

it('prefixes output lines with process name', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output);

    $manager->writePrefix('php', 'Server started on port 8000');

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[php] Server started on port 8000');
});

it('streams prefixed output in foreground mode until processes exit', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output);

    $manager->start('echo', 'echo "hello from echo"');
    $manager->runForeground();

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[echo] hello from echo');
});

it('streams output from multiple processes in foreground mode', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output);

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
    $manager = new ProcessManager($output);

    $manager->start('fast', 'echo done');

    // runForeground should return once the process exits
    $manager->runForeground();

    expect($manager->getPids())->toBeEmpty();
});

it('reports process exit with success status', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output);

    $manager->start('task', 'echo done');
    $manager->runForeground();

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[task] exited');
});

it('reports process exit with failure status', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $manager = new ProcessManager($output);

    $manager->start('fail', 'sh -c "exit 1"');
    $manager->runForeground();

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('[fail] exited with code 1');
});

it('captures the actual command PID not the shell wrapper PID', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $pid = $manager->start('sleep', 'sleep 10');

    // The stored PID must be the sleep process itself.
    // With exec prefix, posix_kill($pid, 0) returns true because the PID
    // is the actual long-running command, not a transient shell wrapper.
    expect(posix_kill($pid, 0))->toBeTrue();

    $manager->stop('sleep');
});

it('reports running processes as running in dev:status', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $pid = $manager->start('sleep', 'sleep 10');

    // Simulate what dev:status does: check the stored PID via posix_kill
    // The PID must still be alive after the start() call returns
    expect(posix_kill($pid, 0))->toBeTrue();

    $manager->stop('sleep');
});

it('reports stopped processes as stopped in dev:status', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $pid = $manager->start('sleep', 'sleep 10');
    $manager->stop('sleep');

    // After stop, the process should no longer be alive
    // Give the OS a moment to clean up
    usleep(50000);
    expect(posix_kill($pid, 0))->toBeFalse();
});

it('correctly tracks PID for long-running processes', function (): void {
    $output = new Output(fopen('php://memory', 'r+'));
    $manager = new ProcessManager($output);

    $pid = $manager->start('sleep', 'sleep 30');

    // Wait a bit to ensure any shell wrapper has had time to exit
    usleep(100000); // 100ms

    // The PID must still be the running process (not a dead shell wrapper)
    expect(posix_kill($pid, 0))->toBeTrue()
        ->and($pid)->toBe($manager->getPid('sleep'));

    $manager->stop('sleep');
});
