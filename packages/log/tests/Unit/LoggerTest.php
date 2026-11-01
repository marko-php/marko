<?php

declare(strict_types=1);

use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\Logger;
use Marko\Log\LogLevel;
use Marko\Log\Tests\Fixtures\RecordingHandler;
use Marko\Testing\Fake\FakeClock;

it('implements LoggerInterface', function (): void {
    $logger = new Logger(
        handlers: [new RecordingHandler()],
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        clock: new FakeClock(),
    );

    expect($logger)->toBeInstanceOf(LoggerInterface::class);
});

it('sends each record to every handler, in order', function (): void {
    $sequence = new ArrayObject();
    $first = new RecordingHandler('first', $sequence);
    $second = new RecordingHandler('second', $sequence);
    $logger = new Logger(
        handlers: [$first, $second],
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        clock: new FakeClock(),
    );

    $logger->info('One');
    $logger->error('Two');

    expect($sequence->getArrayCopy())->toBe(['first', 'second', 'first', 'second'])
        ->and($first->records)->toHaveCount(2)
        ->and($second->records)->toHaveCount(2)
        ->and($second->records[1]->message)->toBe('Two');
});

it('builds the record from the level, message, context, channel and clock', function (): void {
    $handler = new RecordingHandler();
    $logger = new Logger(
        handlers: [$handler],
        channel: 'billing',
        minimumLevel: LogLevel::Debug,
        clock: new FakeClock('2026-01-21 09:30:15'),
    );

    $logger->warning('Card {id} declined', ['id' => 42]);
    $record = $handler->records[0];

    expect($record->level)->toBe(LogLevel::Warning)
        ->and($record->message)->toBe('Card {id} declined')
        ->and($record->context)->toBe(['id' => 42])
        ->and($record->channel)->toBe('billing')
        ->and($record->datetime->format('Y-m-d H:i:s'))->toBe('2026-01-21 09:30:15');
});

it('applies the level threshold before any handler runs', function (): void {
    $handler = new RecordingHandler();
    $logger = new Logger(
        handlers: [$handler],
        channel: 'app',
        minimumLevel: LogLevel::Warning,
        clock: new FakeClock(),
    );

    $logger->debug('skip');
    $logger->info('skip');
    $logger->notice('skip');
    $logger->warning('keep');
    $logger->error('keep');

    expect(array_map(fn ($record) => $record->level, $handler->records))
        ->toBe([LogLevel::Warning, LogLevel::Error]);
});

it('maps every level method to its LogLevel', function (): void {
    $handler = new RecordingHandler();
    $logger = new Logger(
        handlers: [$handler],
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        clock: new FakeClock(),
    );

    $logger->emergency('m');
    $logger->alert('m');
    $logger->critical('m');
    $logger->error('m');
    $logger->warning('m');
    $logger->notice('m');
    $logger->info('m');
    $logger->debug('m');
    $logger->log(LogLevel::Notice, 'm');

    expect(array_map(fn ($record) => $record->level, $handler->records))->toBe([
        LogLevel::Emergency,
        LogLevel::Alert,
        LogLevel::Critical,
        LogLevel::Error,
        LogLevel::Warning,
        LogLevel::Notice,
        LogLevel::Info,
        LogLevel::Debug,
        LogLevel::Notice,
    ]);
});

it('throws when constructed without handlers', function (): void {
    expect(fn () => new Logger(
        handlers: [],
        channel: 'app',
        minimumLevel: LogLevel::Debug,
        clock: new FakeClock(),
    ))->toThrow(InvalidLogConfigException::class);
});
