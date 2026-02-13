<?php

declare(strict_types=1);

namespace Marko\Blog\Http;

use Marko\Blog\Contracts\CookieJarInterface;

class CookieJar implements CookieJarInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function set(
        string $name,
        string $value,
        array $options = [],
    ): void {
        $expires = isset($options['expires']) ? time() + ((int) $options['expires'] * 60) : 0;

        setcookie($name, $value, [
            'expires' => $expires,
            'path' => (string) ($options['path'] ?? '/'),
            'domain' => (string) ($options['domain'] ?? ''),
            'secure' => (bool) ($options['secure'] ?? false),
            'httponly' => (bool) ($options['httpOnly'] ?? false),
            'samesite' => (string) ($options['sameSite'] ?? 'Lax'),
        ]);
    }
}
