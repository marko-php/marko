<?php

declare(strict_types=1);

namespace Marko\Cache\Lock;

/**
 * How far a lock store's mutual exclusion reaches. Callers that need
 * cross-host exclusion check for Distributed and throw otherwise, instead of
 * silently running on every host.
 */
enum LockScope: string
{
    /** Held in memory: excludes callers in the same PHP process only. */
    case Process = 'process';

    /** Held on the local filesystem: excludes processes on the same host only. */
    case Host = 'host';

    /** Held on a shared server: excludes processes on every host. */
    case Distributed = 'distributed';
}
