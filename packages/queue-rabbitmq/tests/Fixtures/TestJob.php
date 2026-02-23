<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq\Tests\Fixtures;

use Marko\Queue\Job;

class TestJob extends Job
{
    public function __construct(
        public string $message = 'test',
    ) {}

    public function handle(): void
    {
        // Test job - does nothing
    }
}
