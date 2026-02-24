<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Integration;

use Marko\Authentication\Contracts\CookieJarInterface;

/**
 * Test cookie jar for integration testing.
 */
class TestCookieJar implements CookieJarInterface
{
    /** @var array<string, string> */
    public array $cookies = [];

    public function get(
        string $name,
    ): ?string {
        return $this->cookies[$name] ?? null;
    }

    public function set(
        string $name,
        string $value,
        int $minutes = 0,
    ): void {
        $this->cookies[$name] = $value;
    }

    public function delete(
        string $name,
    ): void {
        unset($this->cookies[$name]);
    }
}
