<?php

declare(strict_types=1);

use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\NullFailedJobRepository;
use Marko\Queue\Sync\SyncQueue;

test('module.php exists with correct structure', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue('module.php should exist');

    $module = require $modulePath;

    expect($module)->toBeArray();
    expect($module)->toHaveKey('enabled');
    expect($module['enabled'])->toBeTrue();
    expect($module)->toHaveKey('bindings');
    expect($module['bindings'])->toBeArray();
});

test('module.php binds QueueInterface via factory', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(QueueInterface::class);
    expect($module['bindings'][QueueInterface::class])->toBe(SyncQueue::class);
});

test('module.php binds FailedJobRepositoryInterface', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(FailedJobRepositoryInterface::class);
    expect($module['bindings'][FailedJobRepositoryInterface::class])->toBe(NullFailedJobRepository::class);
});

test('NullFailedJobRepository implements interface', function (): void {
    $repository = new NullFailedJobRepository();

    expect($repository)->toBeInstanceOf(FailedJobRepositoryInterface::class);
});

test('NullFailedJobRepository store is no-op', function (): void {
    $repository = new NullFailedJobRepository();
    $failedJob = new FailedJob(
        id: 'test-id',
        queue: 'default',
        payload: '{}',
        exception: 'Test exception',
        failedAt: new DateTimeImmutable(),
    );

    $repository->store($failedJob);

    expect($repository->count())->toBe(0);
    expect($repository->all())->toBe([]);
});

test('NullFailedJobRepository all returns empty array', function (): void {
    $repository = new NullFailedJobRepository();

    expect($repository->all())->toBe([]);
});

test('NullFailedJobRepository find returns null', function (): void {
    $repository = new NullFailedJobRepository();

    expect($repository->find('any-id'))->toBeNull();
});

test('NullFailedJobRepository delete returns false', function (): void {
    $repository = new NullFailedJobRepository();

    expect($repository->delete('any-id'))->toBeFalse();
});

test('NullFailedJobRepository count returns zero', function (): void {
    $repository = new NullFailedJobRepository();

    expect($repository->count())->toBe(0);
});

test('NullFailedJobRepository clear returns zero', function (): void {
    $repository = new NullFailedJobRepository();

    expect($repository->clear())->toBe(0);
});

it('NullFailedJobRepository methods are no-ops', function (): void {
    $repository = new NullFailedJobRepository();
    $failedJob = new FailedJob(
        id: 'test-123',
        queue: 'default',
        payload: '{"class":"TestJob"}',
        exception: 'Exception: Test error',
        failedAt: new DateTimeImmutable(),
    );

    // All methods are no-ops
    $repository->store($failedJob);

    expect($repository->all())->toBe([])
        ->and($repository->find('test-123'))->toBeNull()
        ->and($repository->find('nonexistent'))->toBeNull()
        ->and($repository->delete('test-123'))->toBeFalse()
        ->and($repository->clear())->toBe(0)
        ->and($repository->count())->toBe(0);
});
