<?php

declare(strict_types=1);

namespace Marko\Hashing\Contracts;

interface HasherInterface
{
    /**
     * Hash a value using the hasher's algorithm.
     */
    public function hash(
        string $value,
    ): string;

    /**
     * Verify a value against a hash.
     */
    public function verify(
        string $value,
        string $hash,
    ): bool;

    /**
     * Check if a hash needs to be rehashed due to algorithm/cost changes.
     */
    public function needsRehash(
        string $hash,
    ): bool;

    /**
     * Get the algorithm name for this hasher.
     */
    public function algorithm(): string;
}
