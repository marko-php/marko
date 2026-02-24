<?php

declare(strict_types=1);

use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeLogger;

it('implements LoggerInterface', function () {
    $logger = new FakeLogger();

    expect($logger)->toBeInstanceOf(LoggerInterface::class);
});

it('captures log entries with level, message, and context', function () {
    $logger = new FakeLogger();

    $logger->info('User logged in', ['user_id' => 42]);

    expect($logger->entries)->toHaveCount(1)
        ->and($logger->entries[0]['level'])->toBe(LogLevel::Info)
        ->and($logger->entries[0]['message'])->toBe('User logged in')
        ->and($logger->entries[0]['context'])->toBe(['user_id' => 42]);
});

it('returns all log entries', function () {
    $logger = new FakeLogger();

    $logger->info('First message');
    $logger->error('Second message');
    $logger->debug('Third message');

    expect($logger->entries)->toHaveCount(3)
        ->and($logger->entries[0]['message'])->toBe('First message')
        ->and($logger->entries[1]['message'])->toBe('Second message')
        ->and($logger->entries[2]['message'])->toBe('Third message');
});

it('returns log entries filtered by level', function () {
    $logger = new FakeLogger();

    $logger->info('Info message');
    $logger->error('Error message');
    $logger->info('Another info');

    $infoEntries = $logger->entriesForLevel(LogLevel::Info);

    expect($infoEntries)->toHaveCount(2)
        ->and($infoEntries[0]['message'])->toBe('Info message')
        ->and($infoEntries[1]['message'])->toBe('Another info');
});

it('asserts message was logged', function () {
    $logger = new FakeLogger();

    $logger->info('User logged in');

    expect(fn () => $logger->assertLogged('User logged in'))
        ->not->toThrow(AssertionFailedException::class);
});

it('asserts message was logged at specific level', function () {
    $logger = new FakeLogger();

    $logger->error('Something failed');

    expect(fn () => $logger->assertLogged('Something failed', LogLevel::Error))
        ->not->toThrow(AssertionFailedException::class);
});

it('throws AssertionFailedException when asserting logged message that was not logged', function () {
    $logger = new FakeLogger();

    expect(fn () => $logger->assertLogged('Not logged message'))
        ->toThrow(AssertionFailedException::class);
});

it('asserts nothing was logged', function () {
    $logger = new FakeLogger();

    expect(fn () => $logger->assertNothingLogged())
        ->not->toThrow(AssertionFailedException::class);
});

it('clears all captured entries', function () {
    $logger = new FakeLogger();

    $logger->info('First');
    $logger->error('Second');
    $logger->clear();

    expect($logger->entries)->toBeEmpty();
});
