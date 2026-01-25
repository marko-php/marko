<?php

declare(strict_types=1);

namespace Marko\Session\Contracts;

use SessionHandlerInterface as PhpSessionHandlerInterface;

interface SessionHandlerInterface extends PhpSessionHandlerInterface
{
    /**
     * Perform garbage collection.
     *
     * @param int $max_lifetime Sessions older than this (in seconds) will be deleted
     * @return int|false Number of sessions deleted, or false on failure
     */
    public function gc(int $max_lifetime): int|false;
}
