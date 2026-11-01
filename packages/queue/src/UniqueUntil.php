<?php

declare(strict_types=1);

namespace Marko\Queue;

/**
 * When a unique job's lock is released, so the same job can be pushed again.
 */
enum UniqueUntil: string
{
    /** Released when a worker starts the job: a copy may be queued while this one runs. */
    case Processing = 'processing';

    /** Released when the job completes or fails for the last time: no copy is queued while it is pending or running. */
    case Processed = 'processed';
}
