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
    ) {
        if ($this->name === '' || preg_match(self::INVALID_NAME_PATTERN, $this->name) === 1) {
            throw CookieException::invalidName($this->name);
        }

        if ($this->sameSite === 'None' && !$this->secure) {
            throw CookieException::sameSiteNoneRequiresSecure($this->name);
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function domain(): ?string
    {
        return $this->domain;
    }

    public function toSetCookieString(): string
    {
        $parts = [$this->name . '=' . rawurlencode($this->value)];

        if ($this->expires !== null && $this->expires !== 0) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->expires);
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
