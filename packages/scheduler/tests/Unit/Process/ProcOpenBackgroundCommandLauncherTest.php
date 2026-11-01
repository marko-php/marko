<?php

declare(strict_types=1);

use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Marko\Scheduler\Process\ProcOpenBackgroundCommandLauncher;

/**
 * Poll until the file exists, for at most $seconds.
 */
function waitForLauncherFile(
    string $path,
    float $seconds = 10.0,
): bool {
    $deadline = microtime(true) + $seconds;

    while (microtime(true) < $deadline) {
        clearstatcache(true, $path);

        if (is_file($path)) {
            return true;
        }

        usleep(20_000);
    }

    return false;
}

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/marko-scheduler-launcher-' . bin2hex(random_bytes(6));
    mkdir($this->directory, 0755, true);
    $this->record = $this->directory . '/record.json';
    // Stands in for vendor/bin/marko: waits, then records its argv and working directory.
    $this->markoBinary = $this->directory . '/marko';
    file_put_contents($this->markoBinary, <<<'PHP'
        <?php
        usleep(500_000);
        file_put_contents(__DIR__ . '/record.tmp', json_encode(['argv' => array_slice($argv, 1), 'cwd' => getcwd()]));
        rename(__DIR__ . '/record.tmp', __DIR__ . '/record.json');
        PHP);
    $this->launcher = new ProcOpenBackgroundCommandLauncher(
        phpBinary: PHP_BINARY,
        markoBinary: $this->markoBinary,
        workingDirectory: $this->directory,
    );
});

afterEach(function (): void {
    waitForLauncherFile($this->record, 2.0);
    exec('rm -rf ' . escapeshellarg($this->directory));
});

it('implements BackgroundCommandLauncherInterface', function (): void {
    expect($this->launcher)->toBeInstanceOf(BackgroundCommandLauncherInterface::class);
});

it('starts the command in the background and returns without waiting for it', function (): void {
    $started = microtime(true);
    $this->launcher->launch('app:prune', ['--days=30', "it's \"quoted\" \$HOME"], null);
    $elapsed = microtime(true) - $started;

    // The fake binary sleeps 0.5s before it records anything.
    expect($elapsed)->toBeLessThan(0.4)
        ->and(is_file($this->record))->toBeFalse()
        ->and(waitForLauncherFile($this->record))->toBeTrue();

    $record = json_decode(file_get_contents($this->record), true);

    expect($record['argv'])->toBe(['app:prune', '--days=30', "it's \"quoted\" \$HOME"])
        ->and(realpath($record['cwd']))->toBe(realpath($this->directory));
});

it('runs the command through schedule:run-background with the mutex name when one is given', function (): void {
    $this->launcher->launch('app:prune', ['--days=30'], 'schedule-abc123');

    expect(waitForLauncherFile($this->record))->toBeTrue();

    expect(json_decode(file_get_contents($this->record), true)['argv'])->toBe([
        'schedule:run-background',
        '--schedule-mutex=schedule-abc123',
        '--',
        'app:prune',
        '--days=30',
    ]);
});

it('throws when the marko binary does not exist', function (): void {
    $launcher = new ProcOpenBackgroundCommandLauncher(
        phpBinary: PHP_BINARY,
        markoBinary: $this->directory . '/missing/marko',
        workingDirectory: $this->directory,
    );

    expect(fn () => $launcher->launch('app:prune', [], null))
        ->toThrow(SchedulerException::class, "The marko binary was not found at '$this->directory/missing/marko'");

    // Nothing was launched, so the afterEach has nothing to wait for.
    touch($this->record);
});

it('throws when the process cannot be started', function (): void {
    $launcher = new ProcOpenBackgroundCommandLauncher(
        phpBinary: PHP_BINARY,
        markoBinary: $this->markoBinary,
        workingDirectory: $this->directory . '/does-not-exist',
    );

    expect(fn () => $launcher->launch('app:prune', [], null))
        ->toThrow(SchedulerException::class, "Could not start scheduled command 'app:prune' in the background");

    touch($this->record);
});
