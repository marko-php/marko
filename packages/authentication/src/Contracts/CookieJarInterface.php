<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

interface CookieJarInterface
{
    /**
     * Get a cookie value by name.
     */
    public function get(
        string $name,
    ): ?string;

    /**
     * Set a cookie value.
     */
    public function set(
        string $name,
        string $value,
        int $minutes = 0,
    ): void;

    /**
     * Delete a cookie by name.
     */
    public function delete(
        string $name,
    ): void;
}
