<?php

declare(strict_types=1);

use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDir($path) : unlink($path);
    }
    rmdir($dir);
}

it('writes process entries to JSON file', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir . '/.marko', 0755, true);
    $pidFile = new PidFile($tmpDir);

    $entry = new ProcessEntry(
        name: 'php',
        pid: 1234,
        command: 'php -S localhost:8000',
        port: 8000,
        startedAt: '2026-02-25T00:00:00+00:00',
    );

    $pidFile->write([$entry]);

    $filePath = $tmpDir . '/.marko/dev.json';
    expect(file_exists($filePath))->toBeTrue();

    $data = json_decode(file_get_contents($filePath), true);
    expect($data['processes'])->toHaveCount(1)
        ->and($data['processes'][0]['name'])->toBe('php')
        ->and($data['processes'][0]['pid'])->toBe(1234)
        ->and($data['processes'][0]['command'])->toBe('php -S localhost:8000')
        ->and($data['processes'][0]['port'])->toBe(8000)
        ->and($data['processes'][0]['startedAt'])->toBe('2026-02-25T00:00:00+00:00');

    removeDir($tmpDir);
});

it('creates .marko directory if it does not exist', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    // Do NOT create .marko directory - test that PidFile creates it
    $pidFile = new PidFile($tmpDir);

    $entry = new ProcessEntry(
        name: 'php',
        pid: 1234,
        command: 'php -S localhost:8000',
        port: 8000,
        startedAt: '2026-02-25T00:00:00+00:00',
    );

    $pidFile->write([$entry]);

    expect(is_dir($tmpDir . '/.marko'))->toBeTrue()
        ->and(file_exists($tmpDir . '/.marko/dev.json'))->toBeTrue();

    removeDir($tmpDir);
});

it('stores process name, pid, command, port, and start time', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    $pidFile = new PidFile($tmpDir);

    $entry = new ProcessEntry(
        name: 'vite',
        pid: 5678,
        command: 'npm run dev',
        port: 5173,
        startedAt: '2026-02-25T12:30:00+00:00',
    );

    $pidFile->write([$entry]);
    $entries = $pidFile->read();

    expect($entries[0]->name)->toBe('vite')
        ->and($entries[0]->pid)->toBe(5678)
        ->and($entries[0]->command)->toBe('npm run dev')
        ->and($entries[0]->port)->toBe(5173)
        ->and($entries[0]->startedAt)->toBe('2026-02-25T12:30:00+00:00');

    removeDir($tmpDir);
});

it('checks if a process is still running', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    $pidFile = new PidFile($tmpDir);

    $currentPid = getmypid();
    expect($pidFile->isRunning($currentPid))->toBeTrue()
        ->and($pidFile->isRunning(PHP_INT_MAX))->toBeFalse();

    removeDir($tmpDir);
});

it('removes the PID file via clear method', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    $pidFile = new PidFile($tmpDir);

    $entry = new ProcessEntry(
        name: 'php',
        pid: 1234,
        command: 'php -S localhost:8000',
        port: 8000,
        startedAt: '2026-02-25T00:00:00+00:00',
    );

    $pidFile->write([$entry]);
    expect(file_exists($tmpDir . '/.marko/dev.json'))->toBeTrue();

    $pidFile->clear();
    expect(file_exists($tmpDir . '/.marko/dev.json'))->toBeFalse();

    removeDir($tmpDir);
});

it('detects a process group as running when parent died but child lives', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    $pidFile = new PidFile($tmpDir);

    // Start a process in its own session/group, which spawns a child and exits
    $php = PHP_BINARY;
    $script = <<<'PHP'
        posix_setsid();
        $pid = pcntl_fork();
        if ($pid === 0) {
            // Child: sleep so it stays alive
            sleep(60);
            exit(0);
        }
        // Parent: exit immediately, leaving child alive in the same process group
        exit(0);
    PHP;
    $encoded = base64_encode($script);
    $proc = proc_open(
        "$php -r 'eval(base64_decode(\"$encoded\"));'",
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    $status = proc_get_status($proc);
    $parentPid = $status['pid'];

    // Wait for parent to exit
    usleep(200000);
    proc_close($proc);

    // Parent is dead, but child is still alive in the same process group
    // isRunning now checks both individual PID and process group
    expect($pidFile->isProcessGroupRunning($parentPid))->toBeTrue('process group has alive members')
        ->and($pidFile->isRunning($parentPid))->toBeTrue('isRunning detects group members');

    // Clean up: kill the process group
    posix_kill(-$parentPid, SIGTERM);
    removeDir($tmpDir);
});

it('returns empty array when file does not exist', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    $pidFile = new PidFile($tmpDir);

    $entries = $pidFile->read();

    expect($entries)->toBeEmpty();

    removeDir($tmpDir);
});

it('reads process entries from JSON file', function (): void {
    $tmpDir = sys_get_temp_dir() . '/pid-file-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir . '/.marko', 0755, true);
    $pidFile = new PidFile($tmpDir);

    $entry = new ProcessEntry(
        name: 'php',
        pid: 1234,
        command: 'php -S localhost:8000',
        port: 8000,
        startedAt: '2026-02-25T00:00:00+00:00',
    );

    $pidFile->write([$entry]);
    $entries = $pidFile->read();

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toBeInstanceOf(ProcessEntry::class)
        ->and($entries[0]->name)->toBe('php')
        ->and($entries[0]->pid)->toBe(1234)
        ->and($entries[0]->command)->toBe('php -S localhost:8000')
        ->and($entries[0]->port)->toBe(8000)
        ->and($entries[0]->startedAt)->toBe('2026-02-25T00:00:00+00:00');

    removeDir($tmpDir);
});
