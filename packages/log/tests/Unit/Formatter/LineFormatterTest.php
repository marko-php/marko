<?php

declare(strict_types=1);

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\LogLevel;
use Marko\Log\LogRecord;

it('implements LogFormatterInterface', function () {
    $formatter = new LineFormatter();

    expect($formatter)->toBeInstanceOf(LogFormatterInterface::class);
});

it('formats record with default format', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Test message',
        context: ['key' => 'value'],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter();
    $output = $formatter->format($record);

    expect($output)->toBe("[2026-01-21 10:30:45] app.INFO: Test message {\"key\":\"value\"}\n");
});

it('formats record with empty context', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Debug,
        message: 'Simple message',
        context: [],
        datetime: $datetime,
        channel: 'test',
    );

    $formatter = new LineFormatter();
    $output = $formatter->format($record);

    expect($output)->toBe("[2026-01-21 10:30:45] test.DEBUG: Simple message\n");
});

it('interpolates placeholders in message', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'User {username} logged in',
        context: ['username' => 'john'],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter();
    $output = $formatter->format($record);

    expect($output)->toContain('User john logged in');
});

it('uses custom format string', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Error,
        message: 'Error occurred',
        context: [],
        datetime: $datetime,
        channel: 'api',
    );

    $formatter = new LineFormatter(
        format: '{level} | {message}',
    );
    $output = $formatter->format($record);

    expect($output)->toBe("ERROR | Error occurred\n");
});

it('uses custom date format', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Test',
        context: [],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter(
        format: '[{datetime}] {message}',
        dateFormat: 'd/m/Y H:i',
    );
    $output = $formatter->format($record);

    expect($output)->toBe("[21/01/2026 10:30] Test\n");
});

it('formats all log levels correctly', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $formatter = new LineFormatter(format: '{level}');

    $levels = [
        [LogLevel::Emergency, "EMERGENCY\n"],
        [LogLevel::Alert, "ALERT\n"],
        [LogLevel::Critical, "CRITICAL\n"],
        [LogLevel::Error, "ERROR\n"],
        [LogLevel::Warning, "WARNING\n"],
        [LogLevel::Notice, "NOTICE\n"],
        [LogLevel::Info, "INFO\n"],
        [LogLevel::Debug, "DEBUG\n"],
    ];

    foreach ($levels as [$level, $expected]) {
        $record = new LogRecord(
            level: $level,
            message: 'Test',
            context: [],
            datetime: $datetime,
            channel: 'app',
        );

        expect($formatter->format($record))->toBe($expected);
    }
});

it('handles complex context in JSON', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Test',
        context: [
            'user' => ['id' => 1, 'name' => 'John'],
            'tags' => ['tag1', 'tag2'],
        ],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter(format: '{context}');
    $output = $formatter->format($record);

    expect($output)->toContain('"user":{"id":1,"name":"John"}')
        ->and($output)->toContain('"tags":["tag1","tag2"]');
});
