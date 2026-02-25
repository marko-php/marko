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
