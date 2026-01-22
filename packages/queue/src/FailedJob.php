<?php

declare(strict_types=1);

namespace Marko\Queue;

use DateTimeImmutable;

readonly class FailedJob
{
    public function __construct(
        public string $id,
        public string $queue,
        public string $payload,
        public string $exception,
        public DateTimeImmutable $failedAt,
    ) {}
}
