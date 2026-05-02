<?php

declare(strict_types=1);

use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\File\Driver\FileLogger;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\LogLevel;

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

it('implements LoggerInterface', function () {
    $dir = createTempLogDir();
    $logger = new FileLogger(
        path: $dir,
        channel: 'test',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
    );

    expect($logger)->toBeInstanceOf(LoggerInterface::class);

    cleanupLogDir($dir);
});

it('writes debug log message', function () {
    $dir = createTempLogDir();
    $logger = new FileLogger(
        path: $dir,
        channel: 'test',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Warning,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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

    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
    );

    $logger->info('Test message');

    expect(is_dir($dir))->toBeTrue();

    cleanupLogDir($dir);
});

it('uses daily rotation by default', function () {
    $dir = createTempLogDir();
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
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
    $logger = new FileLogger(
        path: $dir,
        channel: 'api',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
    );

    $logger->info('API request');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('api.INFO');

    cleanupLogDir($dir);
});

it('uses log method with LogLevel enum', function () {
    $dir = createTempLogDir();
    $logger = new FileLogger(
        path: $dir,
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        formatter: new LineFormatter(),
    );

    $logger->log(LogLevel::Warning, 'Warning via log method');

    $files = glob($dir . '/*.log');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('WARNING')
        ->and($content)->toContain('Warning via log method');

    cleanupLogDir($dir);
});
