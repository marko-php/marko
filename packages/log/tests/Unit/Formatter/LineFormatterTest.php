<?php

declare(strict_types=1);

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\LogLevel;
use Marko\Log\LogRecord;

it('implements LogFormatterInterface', function (): void {
    $formatter = new LineFormatter();

    expect($formatter)->toBeInstanceOf(LogFormatterInterface::class);
});

it('formats record with default format', function (): void {
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

it('formats record with empty context', function (): void {
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

it('interpolates placeholders in message', function (): void {
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

it('uses custom format string', function (): void {
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

it('uses custom date format', function (): void {
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

it('formats all log levels correctly', function (): void {
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

it('handles complex context in JSON', function (): void {
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

it(
    'escapes a newline in the message so the output is a single physical line when escaping is enabled',
    function (): void {
        $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
        $record = new LogRecord(
            level: LogLevel::Info,
            message: "Line one\nLine two",
            context: [],
            datetime: $datetime,
            channel: 'app',
        );

        $formatter = new LineFormatter(
            format: '{message}',
            escapeNewlines: true,
        );
        $output = $formatter->format($record);

        expect($output)->toBe("Line one\\nLine two\n")
            ->and(substr_count($output, "\n"))->toBe(1);
    },
);

it('escapes a carriage return in the message when escaping is enabled', function (): void {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: "Line one\rLine two",
        context: [],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter(
        format: '{message}',
        escapeNewlines: true,
    );
    $output = $formatter->format($record);

    expect($output)->toBe("Line one\\rLine two\n");
});

it('preserves the raw newline in the message when escaping is disabled', function (): void {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: "Line one\nLine two",
        context: [],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter(
        format: '{message}',
        escapeNewlines: false,
    );
    $output = $formatter->format($record);

    expect($output)->toContain("Line one\nLine two");
});

it('escapes newlines embedded in context values when escaping is enabled', function (): void {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Test',
        context: ['note' => "multi\nline"],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter(
        format: '{context}',
        escapeNewlines: true,
    );
    $output = $formatter->format($record);

    expect($output)->not->toContain("\n{")
        ->and(substr_count($output, "\n"))->toBe(1);
});

it(
    'produces exactly one trailing newline per formatted record even when the message ends in a newline',
    function (): void {
        $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
        $record = new LogRecord(
            level: LogLevel::Info,
            message: "Trailing newline message\n",
            context: [],
            datetime: $datetime,
            channel: 'app',
        );

        $formatter = new LineFormatter(
            format: '{message}',
            escapeNewlines: true,
        );
        $output = $formatter->format($record);

        expect(substr_count($output, "\n"))->toBe(1)
            ->and($output)->toEndWith("\n");
    },
);

it('defaults escapeNewlines to true when the LineFormatter is constructed without the flag', function (): void {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:45');
    $record = new LogRecord(
        level: LogLevel::Info,
        message: "Line one\nLine two",
        context: [],
        datetime: $datetime,
        channel: 'app',
    );

    $formatter = new LineFormatter(format: '{message}');
    $output = $formatter->format($record);

    expect($output)->toBe("Line one\\nLine two\n");
});
