<?php

declare(strict_types=1);

use Marko\Mcp\Exceptions\McpException;
use Marko\Mcp\Tools\Runtime\Adapters\FileLogReader;

beforeEach(function (): void {
    $this->logsDir = sys_get_temp_dir() . '/marko-mcp-log-reader-' . bin2hex(random_bytes(6));
    mkdir($this->logsDir);
});

afterEach(function (): void {
    foreach (glob($this->logsDir . '/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->logsDir);
});

it('returns the last N lines of the most recently modified log, oldest first', function (): void {
    file_put_contents($this->logsDir . '/old.log', "old-1\nold-2\n");
    touch($this->logsDir . '/old.log', time() - 60);
    file_put_contents($this->logsDir . '/app.log', "line-1\nline-2\nline-3\nline-4\n");

    expect(new FileLogReader($this->logsDir)->readLast(2))->toBe(['line-3', 'line-4']);
});

it('returns every line when fewer exist than requested', function (): void {
    file_put_contents($this->logsDir . '/app.log', "line-1\r\nline-2");

    expect(new FileLogReader($this->logsDir)->readLast(10))->toBe(['line-1', 'line-2']);
});

it('returns nothing for a count below 1 instead of everything but the first lines', function (): void {
    file_put_contents($this->logsDir . '/app.log', "line-1\nline-2\nline-3\n");

    $reader = new FileLogReader($this->logsDir);

    expect($reader->readLast(0))->toBe([])
        ->and($reader->readLast(-2))->toBe([]);
});

it('returns nothing when the directory has no log files', function (): void {
    expect(new FileLogReader($this->logsDir)->readLast(5))->toBe([])
        ->and(new FileLogReader($this->logsDir . '/missing')->readLast(5))->toBe([]);
});

it('tails lines that span chunk boundaries without cutting them', function (): void {
    $lines = [];

    for ($i = 1; $i <= 2000; $i++) {
        $lines[] = "entry-$i " . str_repeat('x', $i % 97);
    }

    file_put_contents($this->logsDir . '/app.log', implode("\n", $lines) . "\n");

    expect(new FileLogReader($this->logsDir)->readLast(300))->toBe(array_slice($lines, -300));
});

it('tails a large log without loading the whole file into memory', function (): void {
    $handle = fopen($this->logsDir . '/app.log', 'wb');
    $line = str_repeat('a', 1023) . "\n";

    // 16 MiB of log lines
    for ($i = 0; $i < 16 * 1024; $i++) {
        fwrite($handle, $line);
    }

    fwrite($handle, "last line\n");
    fclose($handle);

    memory_reset_peak_usage();
    $before = memory_get_usage();

    $entries = new FileLogReader($this->logsDir)->readLast(5);

    expect(memory_get_peak_usage() - $before)->toBeLessThan(2 * 1024 * 1024)
        ->and($entries)->toHaveCount(5)
        ->and($entries[4])->toBe('last line');
});

it('throws a loud error when the latest log cannot be read', function (): void {
    file_put_contents($this->logsDir . '/app.log', "line-1\n");
    chmod($this->logsDir . '/app.log', 0o000);

    try {
        expect(fn () => new FileLogReader($this->logsDir)->readLast(5))
            ->toThrow(McpException::class, 'Cannot read log file');
    } finally {
        chmod($this->logsDir . '/app.log', 0o644);
    }
})->skip(fn (): bool => function_exists('posix_getuid')
    && posix_getuid() === 0, 'root can read any file');
