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
