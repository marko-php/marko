<?php

declare(strict_types=1);

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\Exceptions\LogWriteException;
use Marko\Log\File\Handler\FileLogHandler;
use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Logger;
use Marko\Log\LogLevel;
use Marko\Log\LogRecord;
use Marko\Testing\Fake\FakeClock;

/**
 * A FileLogHandler behind the shared Logger, rotating daily on the clock that stamps the records.
 */
function fileLogger(
    string $path,
    string $channel,
    LogLevel $minimumLevel,
    LogFormatterInterface $formatter,
    FakeClock $clock,
    int $fileMode = 0600,
    int $dirMode = 0700,
): Logger {
    return new Logger(
        handlers: [new FileLogHandler(
            path: $path,
            formatter: $formatter,
            rotation: new DailyRotation($clock),
            fileMode: $fileMode,
            dirMode: $dirMode,
        )],
        channel: $channel,
        minimumLevel: $minimumLevel,
        clock: $clock,
    );
}

function createTempLogDir(): string
{
    $dir = sys_get_temp_dir() . '/marko-log-test-' . bin2hex(random_bytes(8));
    mkdir($dir, 0755, true);

    return $dir;
}

function cleanupLogDir(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '/*');

    if ($files !== false) {
        foreach ($files as $file) {
            unlink($file);
        }
    }

    rmdir($dir);
}

it('implements LogHandlerInterface', function () {
    $dir = createTempLogDir();
    $handler = new FileLogHandler(
        path: $dir,
        formatter: new LineFormatter(),
        rotation: new DailyRotation(new FakeClock()),
    );

    expect($handler)->toBeInstanceOf(LogHandlerInterface::class);

    cleanupLogDir($dir);
});

it('names the file after the record channel', function () {
    $dir = createTempLogDir();
    $handler = new FileLogHandler(
        path: $dir,
        formatter: new LineFormatter(),
        rotation: new DailyRotation(new FakeClock('2026-01-21 09:30:15')),
    );

    $handler->handle(new LogRecord(
        level: LogLevel::Info,
        message: 'Direct',
        context: [],
        datetime: new DateTimeImmutable('2026-01-21 09:30:15'),
        channel: 'billing',
    ));
    $content = file_get_contents($dir . '/billing-2026-01-21.log');

    cleanupLogDir($dir);

    expect($content)->toBe("[2026-01-21 09:30:15] billing.INFO: Direct\n");
});

it('throws LogWriteException when the log directory is not writable', function () {
    $dir = createTempLogDir();
    chmod($dir, 0500);
    $handler = new FileLogHandler(
        path: $dir,
        formatter: new LineFormatter(),
        rotation: new DailyRotation(new FakeClock()),
    );
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Denied',
        context: [],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    try {
        expect(fn () => $handler->handle($record))->toThrow(LogWriteException::class);
    } finally {
        chmod($dir, 0700);
        cleanupLogDir($dir);
    }
})->skip(fn () => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can write to any directory');

it('writes debug log message', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'test',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->debug('Debug message');

    $files = glob($dir . '/*.log');

    expect($files)->toHaveCount(1);

    $content = file_get_contents($files[0]);

    expect($content)->toContain('DEBUG')
        ->and($content)->toContain('Debug message');

    cleanupLogDir($dir);
});

it('writes info log message', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->info('Info message');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('INFO')
        ->and($content)->toContain('Info message');

    cleanupLogDir($dir);
});

it('writes error log message', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->error('Error occurred');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('ERROR')
        ->and($content)->toContain('Error occurred');

    cleanupLogDir($dir);
});

it('writes all log levels', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->emergency('Emergency');
    $logger->alert('Alert');
    $logger->critical('Critical');
    $logger->error('Error');
    $logger->warning('Warning');
    $logger->notice('Notice');
    $logger->info('Info');
    $logger->debug('Debug');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('EMERGENCY')
        ->and($content)->toContain('ALERT')
        ->and($content)->toContain('CRITICAL')
        ->and($content)->toContain('ERROR')
        ->and($content)->toContain('WARNING')
        ->and($content)->toContain('NOTICE')
        ->and($content)->toContain('INFO')
        ->and($content)->toContain('DEBUG');

    cleanupLogDir($dir);
});

it('respects minimum log level threshold', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Warning,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->debug('Should not appear');
    $logger->info('Should not appear');
    $logger->notice('Should not appear');
    $logger->warning('Should appear');
    $logger->error('Should appear');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->not->toContain('DEBUG')
        ->and($content)->not->toContain('INFO')
        ->and($content)->not->toContain('NOTICE')
        ->and($content)->toContain('WARNING')
        ->and($content)->toContain('ERROR');

    cleanupLogDir($dir);
});

it('includes context in log output', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->info('User action', ['user_id' => 42, 'action' => 'login']);

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('"user_id":42')
        ->and($content)->toContain('"action":"login"');

    cleanupLogDir($dir);
});

it('interpolates placeholders in message', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->info('User {name} logged in', ['name' => 'John']);

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('User John logged in');

    cleanupLogDir($dir);
});

it('creates log directory if it does not exist', function () {
    $dir = sys_get_temp_dir() . '/marko-log-test-create-' . bin2hex(random_bytes(8));

    expect(is_dir($dir))->toBeFalse();

    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->info('Test message');

    expect(is_dir($dir))->toBeTrue();

    cleanupLogDir($dir);
});

it('uses daily rotation', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->info('Test message');

    $files = glob($dir . '/*.log');
    $filename = basename($files[0]);

    // Should match pattern: app-YYYY-MM-DD.log
    expect($filename)->toMatch('/^app-\d{4}-\d{2}-\d{2}\.log$/');

    cleanupLogDir($dir);
});

it('appends to existing log file', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->info('First message');
    $logger->info('Second message');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);
    $lines = explode("\n", trim($content));

    expect($lines)->toHaveCount(2)
        ->and($content)->toContain('First message')
        ->and($content)->toContain('Second message');

    cleanupLogDir($dir);
});

it('uses specified channel in log output', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'api',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->info('API request');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('api.INFO');

    cleanupLogDir($dir);
});

it('uses log method with LogLevel enum', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock(),
    );

    $logger->log(LogLevel::Warning, 'Warning via log method');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('WARNING')
        ->and($content)->toContain('Warning via log method');

    cleanupLogDir($dir);
});

it('stamps records with the injected clock', function () {
    $dir = createTempLogDir();
    $clock = new FakeClock('2026-01-21 09:30:15');
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: $clock,
    );

    $logger->info('First');
    $clock->travel('+1 day');
    $logger->info('Second');

    expect(file_get_contents($dir . '/app-2026-01-21.log'))->toContain('[2026-01-21 09:30:15] app.INFO: First')
        ->and(file_get_contents($dir . '/app-2026-01-22.log'))->toContain('[2026-01-22 09:30:15] app.INFO: Second');

    cleanupLogDir($dir);
});

it('creates new log files readable only by the owner by default', function () {
    $dir = createTempLogDir();
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock('2026-01-21 09:30:15'),
    );

    $logger->info('Secret-ish message');
    clearstatcache();
    $mode = fileperms($dir . '/app-2026-01-21.log') & 0777;

    cleanupLogDir($dir);

    expect($mode)->toBe(0600);
});

it('creates a missing log directory readable only by the owner by default', function () {
    $parent = createTempLogDir();
    $dir = $parent . '/logs';
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock('2026-01-21 09:30:15'),
    );

    $logger->info('Hello');
    clearstatcache();
    $mode = fileperms($dir) & 0777;

    cleanupLogDir($dir);
    rmdir($parent);

    expect($mode)->toBe(0700);
});

it('applies configured file and directory modes', function () {
    $parent = createTempLogDir();
    $dir = $parent . '/logs';
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock('2026-01-21 09:30:15'),
        fileMode: 0640,
        dirMode: 0750,
    );

    $logger->info('Hello');
    clearstatcache();
    $fileMode = fileperms($dir . '/app-2026-01-21.log') & 0777;
    $dirMode = fileperms($dir) & 0777;

    cleanupLogDir($dir);
    rmdir($parent);

    expect($fileMode)->toBe(0640)
        ->and($dirMode)->toBe(0750);
});

it('does not change the mode of an existing log file', function () {
    $dir = createTempLogDir();
    $file = $dir . '/app-2026-01-21.log';
    touch($file);
    chmod($file, 0644);
    $logger = fileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
        clock: new FakeClock('2026-01-21 09:30:15'),
    );

    $logger->info('Appended');
    clearstatcache();
    $mode = fileperms($file) & 0777;
    $content = file_get_contents($file);

    cleanupLogDir($dir);

    expect($mode)->toBe(0644)
        ->and($content)->toContain('Appended');
});
