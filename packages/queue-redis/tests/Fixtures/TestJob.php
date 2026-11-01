<?php

declare(strict_types=1);

namespace Marko\Queue\Redis\Tests\Fixtures;

use Marko\Queue\Job;

class TestJob extends Job
{
    /** @var list<string> */
    public static array $handled = [];

    public function __construct(
        public string $message = 'test',
    ) {}

    public function handle(): void
    {
        self::$handled[] = $this->message;
    }
}
