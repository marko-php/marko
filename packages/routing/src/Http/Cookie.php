<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Routing\Exceptions\CookieException;

readonly class Cookie
{
    private const string INVALID_NAME_PATTERN = '/[\x00-\x20\x7F()<>@,;:\\\\"\/\[\]?={}]/';

    /**
     * @throws CookieException
     */
    public function __construct(
        private string $name,
        private string $value = '',
        private ?int $expires = null,
        private ?string $path = null,
        private ?string $domain = null,
        private bool $secure = false,
        private bool $httpOnly = false,
        private ?string $sameSite = null,
        private ?int $maxAge = null,
    ) {
        if (
            $this->name === ''
            || preg_match(self::INVALID_NAME_PATTERN, $this->name) === 1
        ) {
            throw CookieException::invalidName($this->name);
        }

        if (
            $this->sameSite === 'None'
            && !$this->secure
        ) {
            throw CookieException::sameSiteNoneRequiresSecure($this->name);
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The raw value, before the URL-encoding applied in the Set-Cookie line.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * The expiry as a Unix timestamp; null (or 0) for a browser session cookie.
     */
    public function expires(): ?int
    {
        return $this->expires;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function domain(): ?string
    {
        return $this->domain;
    }

    public function secure(): bool
    {
        return $this->secure;
    }

    public function httpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function sameSite(): ?string
    {
        return $this->sameSite;
    }

    /**
     * The lifetime in seconds from when the client receives the cookie; null when not set.
     * Zero or negative deletes the cookie. Browsers prefer Max-Age over Expires (RFC 6265 §5.3).
     */
    public function maxAge(): ?int
    {
        return $this->maxAge;
    }

    /**
     * The Set-Cookie header value. Max-Age is sent as given (zero for a negative value);
     * no Expires attribute is derived from it, so pass $expires as well for clients that
     * predate Max-Age.
     */
    public function toSetCookieString(): string
    {
        $parts = [$this->name . '=' . rawurlencode($this->value)];

        if (
            $this->expires !== null
            && $this->expires !== 0
        ) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->expires);
        }

        if ($this->maxAge !== null) {
            $parts[] = 'Max-Age=' . max(0, $this->maxAge);
        }

        if ($this->path !== null) {
            $parts[] = 'Path=' . $this->path;
        }

        if ($this->domain !== null) {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($this->sameSite !== null) {
            $parts[] = 'SameSite=' . $this->sameSite;
        }

        return implode('; ', $parts);
    }
}
