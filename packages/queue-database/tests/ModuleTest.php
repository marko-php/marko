<?php

declare(strict_types=1);

use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;

test('module.php exists with correct structure', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue('module.php should exist');

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('enabled')
        ->and($module['enabled'])->toBeTrue()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

test('module.php binds QueueInterface via factory', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(QueueInterface::class)
        ->and($module['bindings'][QueueInterface::class])->toBeInstanceOf(Closure::class);
});

test('module.php binds FailedJobRepositoryInterface', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(FailedJobRepositoryInterface::class)
        ->and($module['bindings'][FailedJobRepositoryInterface::class])->toBe(
            DatabaseFailedJobRepository::class,
        );
});
