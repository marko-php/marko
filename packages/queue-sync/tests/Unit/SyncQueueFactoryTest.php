<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Tests\Unit;

use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\Factory\SyncQueueFactory;
use Marko\Queue\Sync\SyncQueue;
use Marko\Testing\Fake\FakeConfigRepository;

it('uses FakeConfigRepository in SyncQueueFactoryTest', function (): void {
    $config = new FakeConfigRepository(['queue.driver' => 'sync']);

    expect($config)->toBeInstanceOf(FakeConfigRepository::class);
});

it('SyncQueueFactory creates configured queue', function (): void {
    $config = createQueueConfigMock();

    $factory = new SyncQueueFactory($config);
    $queue = $factory->create();

    expect($queue)->toBeInstanceOf(QueueInterface::class)
        ->and($queue)->toBeInstanceOf(SyncQueue::class);
});

function createQueueConfigMock(
    string $driver = 'sync',
    string $queue = 'default',
): QueueConfig {
    $repository = new FakeConfigRepository([
        'queue.driver' => $driver,
        'queue.queue' => $queue,
        'queue.connection' => 'default',
        'queue.retry_after' => 90,
        'queue.max_attempts' => 3,
    ]);

    return new QueueConfig($repository);
}
