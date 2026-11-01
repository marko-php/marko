<?php

declare(strict_types=1);

namespace Marko\Queue;

/**
 * A shared token that tells every queue worker to restart.
 *
 * queue:restart calls request(); each worker remembers current() when it starts and exits once
 * the token changes, so a restart requested before a worker started never stops it.
 */
interface RestartSignalInterface
{
    /**
     * Record a new restart token.
     */
    public function request(): void;

    /**
     * The current restart token, or null if no restart was ever requested.
     */
    public function current(): ?string;
}
