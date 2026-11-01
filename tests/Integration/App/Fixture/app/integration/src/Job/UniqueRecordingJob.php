<?php

declare(strict_types=1);

namespace Marko\Integration\Fixture\Job;

use Marko\Queue\Job;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueUntil;

/**
 * A unique job keyed by $key that leaves a marker file behind, so a test can
 * prove a duplicate push was skipped and the lock was released after the run.
 */
class UniqueRecordingJob extends Job implements UniqueJobInterface
{
    public function __construct(
        public string $key,
        public string $markerPath,
    ) {}

    public function handle(): void
    {
        file_put_contents($this->markerPath, "unique job $this->key ran");
    }

    public function uniqueId(): string
    {
        return $this->key;
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function uniqueUntil(): UniqueUntil
    {
        return UniqueUntil::Processed;
    }
}
