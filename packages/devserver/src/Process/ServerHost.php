<?php

declare(strict_types=1);

namespace Marko\DevServer\Process;

use Marko\DevServer\Exceptions\DevServerException;

/**
 * The host the PHP development server binds to.
 *
 * Accepts a hostname, an IPv4 address, or an IPv6 address with or without one
 * pair of surrounding brackets. The address is stored unbracketed and bracketed
 * again wherever it is used in a URI.
 */
readonly class ServerHost
{
    private const string HOSTNAME_PATTERN = '/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?)*\z/';

    /**
     * @param string $address The hostname or IP address, IPv6 without brackets
     */
    private function __construct(
        public string $address,
    ) {}

    /**
     * @throws DevServerException If the value is not a hostname or IP address
     */
    public static function fromString(
        string $host,
    ): self {
        if (preg_match('/\A\[([^\[\]]*)]\z/', $host, $matches) === 1) {
            if (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw DevServerException::invalidHost($host);
            }

            return new self($matches[1]);
        }

        if (
            filter_var($host, FILTER_VALIDATE_IP) !== false
            || preg_match(self::HOSTNAME_PATTERN, $host) === 1
        ) {
            return new self($host);
        }

        throw DevServerException::invalidHost($host);
    }

    /**
     * Bracket an IPv6 literal so it can be used in a URI; other hosts are returned unchanged.
     */
    public static function formatForUri(
        string $host,
    ): string {
        return (str_contains($host, ':') && !str_starts_with($host, '['))
            ? "[$host]"
            : $host;
    }

    /**
     * The host as it appears in a URI or in `php -S HOST:PORT`.
     */
    public function forUri(): string
    {
        return self::formatForUri($this->address);
    }

    /**
     * The host to open in a browser: wildcard addresses cannot be browsed to, so they become localhost.
     */
    public function forBrowser(): string
    {
        return in_array($this->address, ['0.0.0.0', '::'], true) ? 'localhost' : $this->forUri();
    }
}
