<?php

declare(strict_types=1);

use Marko\Log\LogLevel;

it('has all PSR-3 log levels', function () {
    expect(LogLevel::cases())->toHaveCount(8)
        ->and(LogLevel::Emergency->value)->toBe('emergency')
        ->and(LogLevel::Alert->value)->toBe('alert')
        ->and(LogLevel::Critical->value)->toBe('critical')
        ->and(LogLevel::Error->value)->toBe('error')
        ->and(LogLevel::Warning->value)->toBe('warning')
        ->and(LogLevel::Notice->value)->toBe('notice')
        ->and(LogLevel::Info->value)->toBe('info')
        ->and(LogLevel::Debug->value)->toBe('debug');
});

it('returns correct severity for each level', function () {
    expect(LogLevel::Emergency->severity())->toBe(0)
        ->and(LogLevel::Alert->severity())->toBe(1)
        ->and(LogLevel::Critical->severity())->toBe(2)
        ->and(LogLevel::Error->severity())->toBe(3)
        ->and(LogLevel::Warning->severity())->toBe(4)
        ->and(LogLevel::Notice->severity())->toBe(5)
        ->and(LogLevel::Info->severity())->toBe(6)
        ->and(LogLevel::Debug->severity())->toBe(7);
});

it('checks emergency meets all thresholds', function () {
    expect(LogLevel::Emergency->meetsThreshold(LogLevel::Emergency))->toBeTrue()
        ->and(LogLevel::Emergency->meetsThreshold(LogLevel::Debug))->toBeTrue();
});

it('checks debug only meets debug threshold', function () {
    expect(LogLevel::Debug->meetsThreshold(LogLevel::Debug))->toBeTrue()
        ->and(LogLevel::Debug->meetsThreshold(LogLevel::Info))->toBeFalse()
        ->and(LogLevel::Debug->meetsThreshold(LogLevel::Error))->toBeFalse();
});

it('checks info meets info and higher thresholds', function () {
    expect(LogLevel::Info->meetsThreshold(LogLevel::Debug))->toBeTrue()
        ->and(LogLevel::Info->meetsThreshold(LogLevel::Info))->toBeTrue()
        ->and(LogLevel::Info->meetsThreshold(LogLevel::Notice))->toBeFalse();
});

it('checks error meets error and higher thresholds', function () {
    expect(LogLevel::Error->meetsThreshold(LogLevel::Debug))->toBeTrue()
        ->and(LogLevel::Error->meetsThreshold(LogLevel::Error))->toBeTrue()
        ->and(LogLevel::Error->meetsThreshold(LogLevel::Critical))->toBeFalse();
});

it('returns uppercase name', function () {
    expect(LogLevel::Emergency->upperName())->toBe('EMERGENCY')
        ->and(LogLevel::Debug->upperName())->toBe('DEBUG')
        ->and(LogLevel::Info->upperName())->toBe('INFO');
});

it('can be created from string value', function () {
    expect(LogLevel::from('error'))->toBe(LogLevel::Error)
        ->and(LogLevel::from('debug'))->toBe(LogLevel::Debug)
        ->and(LogLevel::tryFrom('invalid'))->toBeNull();
});
