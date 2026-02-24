<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

interface RememberTokenStorageInterface
{
    /**
     * Clear all expired remember tokens from storage.
     *
     * @return int Number of tokens cleared
     */
    public function clearExpiredTokens(): int;

    /**
     * Clear all remember tokens from storage.
     *
     * @return int Number of tokens cleared
     */
    public function clearAllTokens(): int;
}
