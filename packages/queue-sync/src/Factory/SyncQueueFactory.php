<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Factory;

use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\SyncQueue;

readonly class SyncQueueFactory
{
    public function __construct(
        private QueueConfig $config,
    ) {}

    public function create(): QueueInterface
    {
        return new SyncQueue();
    }
}
