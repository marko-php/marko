<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Command\DevStatusCommand;
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;

/**
 * Helper to create a temp dir for dev:status tests.
 */
function devStatusTmpDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/dev-status-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    return $tmpDir;
}

/**
 * Recursively remove a directory.
 */
function devStatusRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        is_dir($path) ? devStatusRemoveDir($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * Execute DevStatusCommand and return output as string.
 *
 * @param resource $stream
 */
function devStatusExecute(DevStatusCommand $command, mixed &$stream): string
{
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);
    rewind($stream);

    return stream_get_contents($stream);
}

it('has Command attribute with name dev:status', function (): void {
    $reflection = new ReflectionClass(DevStatusCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('dev:status');
});

it('reads process list from PID file', function (): void {
    $tmpDir = devStatusTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', getmypid(), 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    $command = new DevStatusCommand($pidFile);
    $result = devStatusExecute($command, $stream);

    expect($result)->toContain('php');

    devStatusRemoveDir($tmpDir);
});

it('displays process name, PID, status, and port', function (): void {
    $tmpDir = devStatusTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', getmypid(), 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    $command = new DevStatusCommand($pidFile);
    $result = devStatusExecute($command, $stream);

    expect($result)->toContain('php')
        ->and($result)->toContain((string) getmypid())
        ->and($result)->toContain('8000');

    devStatusRemoveDir($tmpDir);
});

it('shows running status for alive processes', function (): void {
    $tmpDir = devStatusTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', getmypid(), 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    $command = new DevStatusCommand($pidFile);
    $result = devStatusExecute($command, $stream);

    expect($result)->toContain('running');

    devStatusRemoveDir($tmpDir);
});

it('shows stopped status for dead processes', function (): void {
    $tmpDir = devStatusTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    $command = new DevStatusCommand($pidFile);
    $result = devStatusExecute($command, $stream);

    expect($result)->toContain('stopped');

    devStatusRemoveDir($tmpDir);
});

it('outputs message when no services are running', function (): void {
    $tmpDir = devStatusTmpDir();
    $pidFile = new PidFile($tmpDir);
    // Write no entries (empty PID file scenario - just don't write anything)

    $command = new DevStatusCommand($pidFile);
    $result = devStatusExecute($command, $stream);

    expect($result)->toContain('No development services running.');

    devStatusRemoveDir($tmpDir);
});
