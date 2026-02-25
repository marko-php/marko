<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Command\DevDownCommand;
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;

function devDownTmpDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/dev-down-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    return $tmpDir;
}

function devDownRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        is_dir($path) ? devDownRemoveDir($path) : unlink($path);
    }
    rmdir($dir);
}

it('has Command attribute with name dev:down and alias down', function (): void {
    $reflection = new ReflectionClass(DevDownCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $attr = $attributes[0]->newInstance();

    expect($attr->name)->toBe('dev:down')
        ->and($attr->aliases)->toContain('down');
});

it('reads process list from PID file', function (): void {
    $tmpDir = devDownTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command = new DevDownCommand($pidFile);

    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('php');

    devDownRemoveDir($tmpDir);
});

it('stops processes recorded in PID file', function (): void {
    $tmpDir = devDownTmpDir();
    $pidFile = new PidFile($tmpDir);

    // Start a real short-lived process and record its PID
    $proc = proc_open('sleep 60', [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $status = proc_get_status($proc);
    $pid = $status['pid'];

    $pidFile->write([
        new ProcessEntry('sleep', $pid, 'sleep 60', 0, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command = new DevDownCommand($pidFile);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping sleep');

    proc_close($proc);
    devDownRemoveDir($tmpDir);
});

it('cleans up PID file after stopping', function (): void {
    $tmpDir = devDownTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command = new DevDownCommand($pidFile);
    $command->execute(new Input([]), $output);

    expect($pidFile->read())->toBeEmpty();

    devDownRemoveDir($tmpDir);
});

it('outputs message when no services are running', function (): void {
    $tmpDir = devDownTmpDir();
    $pidFile = new PidFile($tmpDir);
    // No entries written - PID file does not exist

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command = new DevDownCommand($pidFile);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('No development services running.');

    devDownRemoveDir($tmpDir);
});

it('handles already-dead processes gracefully', function (): void {
    $tmpDir = devDownTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command = new DevDownCommand($pidFile);

    // Should not throw
    $exitCode = $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($exitCode)->toBe(0)
        ->and($content)->toContain('already stopped');

    devDownRemoveDir($tmpDir);
});

it('runs docker compose down for Docker processes', function (): void {
    $tmpDir = devDownTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('docker', 0, 'docker compose -f compose.yaml up -d', 0, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command = new DevDownCommand($pidFile);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping Docker: docker compose -f compose.yaml down');

    devDownRemoveDir($tmpDir);
});
