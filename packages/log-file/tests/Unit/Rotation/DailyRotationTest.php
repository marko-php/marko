<?php

declare(strict_types=1);

use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\File\Rotation\RotationStrategyInterface;

it('implements RotationStrategyInterface', function () {
    $rotation = new DailyRotation();

    expect($rotation)->toBeInstanceOf(RotationStrategyInterface::class);
});

it('generates path with current date', function () {
    $now = new DateTimeImmutable('2026-01-21');
    $rotation = new DailyRotation($now);

    $path = $rotation->getCurrentPath('/var/log', 'app');

    expect($path)->toBe('/var/log/app-2026-01-21.log');
});

it('generates different paths for different dates', function () {
    $day1 = new DateTimeImmutable('2026-01-21');
    $day2 = new DateTimeImmutable('2026-01-22');

    $rotation1 = new DailyRotation($day1);
    $rotation2 = new DailyRotation($day2);

    $path1 = $rotation1->getCurrentPath('/var/log', 'app');
    $path2 = $rotation2->getCurrentPath('/var/log', 'app');

    expect($path1)->toBe('/var/log/app-2026-01-21.log')
        ->and($path2)->toBe('/var/log/app-2026-01-22.log')
        ->and($path1)->not->toBe($path2);
});

it('handles trailing slash in base path', function () {
    $now = new DateTimeImmutable('2026-01-21');
    $rotation = new DailyRotation($now);

    $path = $rotation->getCurrentPath('/var/log/', 'app');

    expect($path)->toBe('/var/log/app-2026-01-21.log');
});

it('uses different channel names', function () {
    $now = new DateTimeImmutable('2026-01-21');
    $rotation = new DailyRotation($now);

    $appPath = $rotation->getCurrentPath('/var/log', 'app');
    $apiPath = $rotation->getCurrentPath('/var/log', 'api');

    expect($appPath)->toBe('/var/log/app-2026-01-21.log')
        ->and($apiPath)->toBe('/var/log/api-2026-01-21.log');
});

it('indicates no rotation needed for current date file', function () {
    $now = new DateTimeImmutable('2026-01-21');
    $rotation = new DailyRotation($now);

    $currentPath = $rotation->getCurrentPath('/var/log', 'app');

    expect($rotation->needsRotation($currentPath))->toBeFalse();
});

it('indicates rotation needed for previous date file', function () {
    $now = new DateTimeImmutable('2026-01-22');
    $rotation = new DailyRotation($now);

    $oldPath = '/var/log/app-2026-01-21.log';

    expect($rotation->needsRotation($oldPath))->toBeTrue();
});

it('handles files without date pattern', function () {
    $rotation = new DailyRotation();

    expect($rotation->needsRotation('/var/log/app.log'))->toBeFalse();
});
