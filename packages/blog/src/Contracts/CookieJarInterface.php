<?php

declare(strict_types=1);

namespace Marko\Blog\Contracts;

interface CookieJarInterface
{
    /**
     * Set a cookie with the given name, value, and options.
     *
     * @param array<string, mixed> $options Cookie options (expires, httpOnly, secure, sameSite, path, domain)
     */
    public function set(
        string $name,
        string $value,
        array $options = [],
    ): void;
}
