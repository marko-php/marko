<?php

declare(strict_types=1);

use Marko\Log\LogLevel;
use Marko\Log\LogRecord;

it('stores all properties immutably', function () {
    $datetime = new DateTimeImmutable('2026-01-21 10:30:00');
    $record = new LogRecord(
        level: LogLevel::Error,
        message: 'Test message',
        context: ['key' => 'value'],
        datetime: $datetime,
        channel: 'app',
    );

    expect($record->level)->toBe(LogLevel::Error)
        ->and($record->message)->toBe('Test message')
        ->and($record->context)->toBe(['key' => 'value'])
        ->and($record->datetime)->toBe($datetime)
        ->and($record->channel)->toBe('app');
});

it('interpolates placeholders in message', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'User {username} logged in from {ip}',
        context: ['username' => 'john', 'ip' => '192.168.1.1'],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->interpolatedMessage())->toBe('User john logged in from 192.168.1.1');
});

it('returns original message when no placeholders', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Simple message without placeholders',
        context: ['key' => 'value'],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->interpolatedMessage())->toBe('Simple message without placeholders');
});

it('returns original message when context is empty', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Message with {placeholder}',
        context: [],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->interpolatedMessage())->toBe('Message with {placeholder}');
});

it('handles numeric context values', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Order {order_id} has {count} items',
        context: ['order_id' => 123, 'count' => 5],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->interpolatedMessage())->toBe('Order 123 has 5 items');
});

it('handles float context values', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Total: {amount}',
        context: ['amount' => 99.99],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->interpolatedMessage())->toBe('Total: 99.99');
});

it('ignores non-stringable context values', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Data: {array}',
        context: ['array' => ['nested' => 'value']],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->interpolatedMessage())->toBe('Data: {array}');
});

it('returns context as JSON', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Test',
        context: ['user_id' => 42, 'action' => 'login'],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->contextAsJson())->toBe('{"user_id":42,"action":"login"}');
});

it('returns empty string when context is empty', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Test',
        context: [],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->contextAsJson())->toBe('');
});

it('preserves unicode in context JSON', function () {
    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Test',
        context: ['name' => '日本語'],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->contextAsJson())->toBe('{"name":"日本語"}');
});

it('handles stringable objects in interpolation', function () {
    $stringable = new class ()
    {
        public function __toString(): string
        {
            return 'stringable-value';
        }
    };

    $record = new LogRecord(
        level: LogLevel::Info,
        message: 'Value: {obj}',
        context: ['obj' => $stringable],
        datetime: new DateTimeImmutable(),
        channel: 'app',
    );

    expect($record->interpolatedMessage())->toBe('Value: stringable-value');
});
