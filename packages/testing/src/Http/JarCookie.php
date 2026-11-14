<?php

declare(strict_types=1);

namespace Marko\Testing\Http;

/**
 * One cookie in the TestClient cookie jar, with the scope a browser would keep for it.
 * The jar holds one entry per (name, domain, path), so same-name cookies on
 * different paths or domains live side by side.
 */
readonly class JarCookie
{
    /**
     * @param string|null $domain the host or domain the cookie belongs to (lowercase, no leading dot);
     *                            null for a cookie added with withCookie() without a domain, sent to any host
     * @param bool $hostOnly true when the response set no Domain attribute: the cookie goes to $domain
     *                       exactly, not to its subdomains
     * @param int|null $expiresAt the Unix timestamp the cookie expires at, from Max-Age (counted from the
     *                            client clock when stored) or else Expires; null for a session cookie
     * @param string|null $sameSite Strict, Lax or None; null when the cookie had no SameSite attribute,
     *                              which the jar treats like None
     */
    public function __construct(
        public string $name,
        public string $value,
        public ?string $domain,
        public string $path,
        public bool $secure = false,
        public bool $hostOnly = false,
        public ?int $expiresAt = null,
        public ?string $sameSite = null,
    ) {}

    /**
     * Whether the cookie has expired at $now (a Unix timestamp). A session cookie never does.
     */
    public function isExpired(
        int $now,
    ): bool {
        return $this->expiresAt !== null
            && $this->expiresAt <= $now;
    }

    /**
     * Whether a cross-site request with $method carries this cookie: never for SameSite=Strict,
     * only for a top-level GET for SameSite=Lax, always for None or no SameSite at all.
     */
    public function allowsCrossSite(
        string $method,
    ): bool {
        return match (strtolower((string) $this->sameSite)) {
            'strict' => false,
            'lax' => strtoupper($method) === 'GET',
            default => true,
        };
    }

    /**
     * Whether a request to $host, $path, over HTTPS or not, carries this cookie (RFC 6265 §5.4).
     */
    public function matches(
        string $host,
        string $path,
        bool $secure,
    ): bool {
        if (
            $this->secure
            && !$secure
        ) {
            return false;
        }

        return $this->matchesHost($host)
            && self::pathMatches($path, $this->path);
    }

    private function matchesHost(
        string $host,
    ): bool {
        if ($this->domain === null) {
            return true;
        }

        if ($this->hostOnly) {
            return $host === $this->domain;
        }

        return self::domainMatches($host, $this->domain);
    }

    /**
     * RFC 6265 §5.1.3: $host is $domain, or a subdomain of it. An IP address only matches itself.
     */
    public static function domainMatches(
        string $host,
        string $domain,
    ): bool {
        if ($host === $domain) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) === false
            && str_ends_with($host, '.' . $domain);
    }

    /**
     * RFC 6265 §5.1.4: the request path is the cookie path, or below it on a `/` boundary.
     */
    public static function pathMatches(
        string $requestPath,
        string $cookiePath,
    ): bool {
        if ($requestPath === $cookiePath) {
            return true;
        }

        if (!str_starts_with($requestPath, $cookiePath)) {
            return false;
        }

        return str_ends_with($cookiePath, '/')
            || $requestPath[strlen($cookiePath)] === '/';
    }

    /**
     * RFC 6265 §5.1.4: the path a cookie without a Path attribute gets, the request path up to its last `/`.
     */
    public static function defaultPath(
        string $requestPath,
    ): string {
        if (!str_starts_with($requestPath, '/')) {
            return '/';
        }

        $lastSlash = (int) strrpos($requestPath, '/');

        return $lastSlash === 0 ? '/' : substr($requestPath, 0, $lastSlash);
    }
}
