<?php

declare(strict_types=1);

use Marko\DevAi\Process\CommandRunner;

describe('CommandRunner', function (): void {
    it('captures both stdout and stderr from a command', function (): void {
        $runner = new CommandRunner();
        $result = $runner->run('sh', ['-c', 'echo hello; echo world >&2']);

        expect($result['stdout'])->toContain('hello')
            ->and($result['stderr'])->toContain('world');
    });

    it('preserves the child process exit code', function (): void {
        $runner = new CommandRunner();
        $result = $runner->run('sh', ['-c', 'exit 42']);

        expect($result['exitCode'])->toBe(42);
    });

    it('completes without hanging when the child writes more than 64KB to stderr', function (): void {
        $runner = new CommandRunner();
        // Write ~128KB to stderr and some bytes to stdout; if sequential drain the parent blocks
        $script = 'printf "%s" "$(head -c 131072 /dev/zero | tr "\\0" "x")" >&2; echo ok';
        $started = microtime(true);
        $result = $runner->run('sh', ['-c', $script]);
        $elapsed = microtime(true) - $started;

        expect($elapsed)->toBeLessThan(10.0)
            ->and($result['stdout'])->toContain('ok')
            ->and(strlen($result['stderr']))->toBeGreaterThan(65536);
    })->group('integration');

    it('returns the proc_open failure shape when the process cannot start', function (): void {
        $runner = new CommandRunner();
        // Pass a command that proc_open will fail on by using a completely invalid path
        // We mock this by testing the early-return path indirectly — since proc_open
        // failure depends on the system, we test the return shape contract instead
        // by passing a non-executable path on most systems
        $result = $runner->run('/nonexistent/binary/that/does/not/exist/xyz123');

        expect($result)->toHaveKey('exitCode')
            ->and($result)->toHaveKey('stdout')
            ->and($result)->toHaveKey('stderr');
    });
});
