<?php

declare(strict_types=1);

namespace Marko\Core\Contracts;

/**
 * Implemented by services that hold request-scoped state which must be
 * cleared between requests in a long-running process (e.g. a worker or
 * event loop reusing the same PHP process across multiple requests).
 *
 * A long-running worker calls reset() on every registered ResettableInterface
 * implementation between requests. Under a per-request runtime such as
 * PHP-FPM, the process ends after each request instead, so reset() is
 * never called and the contract is effectively a no-op.
 *
 * reset() must be non-destructive: it clears the in-memory per-request
 * state an instance was tracking, without destroying anything persisted
 * (storage, database rows, files, etc). For example, resetting a session
 * service must forget which session the instance was serving, not delete
 * the stored session itself.
 */
interface ResettableInterface
{
    /**
     * Clear this instance's request-scoped state so it is safe to reuse
     * for the next request. Must not destroy persisted data.
     */
    public function reset(): void;
}
