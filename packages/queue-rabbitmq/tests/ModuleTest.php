<?php

declare(strict_types=1);

use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\RabbitmqFailedJobRepository;
use Marko\Queue\Rabbitmq\RabbitmqQueue;

test('it binds QueueInterface to RabbitmqQueue', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(QueueInterface::class)
        ->and($module['bindings'][QueueInterface::class])->toBe(RabbitmqQueue::class);
});

test('it binds FailedJobRepositoryInterface to RabbitmqFailedJobRepository', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(FailedJobRepositoryInterface::class)
        ->and($module['bindings'][FailedJobRepositoryInterface::class])->toBe(
            RabbitmqFailedJobRepository::class,
        );
});

test('it returns valid module configuration array', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

test('it has marko module flag in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('extra')
        ->and($composer['extra'])->toHaveKey('marko')
        ->and($composer['extra']['marko'])->toHaveKey('module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});
