<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Command\DevOpenCommand;
use Marko\DevServer\Exceptions\DevServerException;
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;

/**
 * Helper to create a temp dir for dev:open tests.
 */
function devOpenTmpDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/dev-open-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    return $tmpDir;
}

/**
 * Recursively remove a directory.
 */
function devOpenRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        is_dir($path) ? devOpenRemoveDir($path) : unlink($path);
    }
    rmdir($dir);
}

it('has Command attribute with name dev:open and alias open', function (): void {
    $reflection = new ReflectionClass(DevOpenCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('dev:open')
        ->and($attributes[0]->newInstance()->aliases)->toBe(['open']);
});

it('throws DevServerException when no services are running', function (): void {
    $tmpDir = devOpenTmpDir();
    $pidFile = new PidFile($tmpDir);

    $opener = function (string $url): void {
        throw new RuntimeException('Should not be called');
    };

    $command = new DevOpenCommand($pidFile, $opener);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $command->execute(new Input([]), $output);

    devOpenRemoveDir($tmpDir);
})->throws(DevServerException::class, 'No running development environment found');

it('throws DevServerException when PHP server process is not running', function (): void {
    $tmpDir = devOpenTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    $opener = function (string $url): void {
        throw new RuntimeException('Should not be called');
    };

    $command = new DevOpenCommand($pidFile, $opener);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $command->execute(new Input([]), $output);

    devOpenRemoveDir($tmpDir);
})->throws(DevServerException::class, 'PHP server is not running');

it('opens the browser with the correct URL for a running PHP server', function (): void {
    $tmpDir = devOpenTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', getmypid(), 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    $openedUrl = null;
    $opener = function (string $url) use (&$openedUrl): void {
        $openedUrl = $url;
    };

    $command = new DevOpenCommand($pidFile, $opener);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($openedUrl)->toBe('http://localhost:8000')
        ->and($result)->toContain('Opening http://localhost:8000');

    devOpenRemoveDir($tmpDir);
});

it('uses the correct port from the process entry', function (): void {
    $tmpDir = devOpenTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', getmypid(), 'php -S localhost:9090', 9090, '2026-02-25T10:00:00+00:00'),
    ]);

    $openedUrl = null;
    $opener = function (string $url) use (&$openedUrl): void {
        $openedUrl = $url;
    };

    $command = new DevOpenCommand($pidFile, $opener);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    expect($openedUrl)->toBe('http://localhost:9090');

    devOpenRemoveDir($tmpDir);
});

it('finds the PHP server among multiple processes', function (): void {
    $tmpDir = devOpenTmpDir();
    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('docker', getmypid(), 'docker compose up', 0, '2026-02-25T10:00:00+00:00'),
        new ProcessEntry('frontend', getmypid(), 'npm run dev', 0, '2026-02-25T10:00:00+00:00'),
        new ProcessEntry('php', getmypid(), 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    $openedUrl = null;
    $opener = function (string $url) use (&$openedUrl): void {
        $openedUrl = $url;
    };

    $command = new DevOpenCommand($pidFile, $opener);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    expect($openedUrl)->toBe('http://localhost:8000');

    devOpenRemoveDir($tmpDir);
});
