<?php

declare(strict_types=1);

namespace Marko\Scheduler;

readonly class ScheduleRunResult
{
    public function __construct(
        public int $executed,
        public int $failed,
        public int $skipped,
        public int $skippedByCondition = 0,
        public int $launched = 0,
        public int $skippedByServer = 0,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }
}
