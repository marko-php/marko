<?php

declare(strict_types=1);

namespace Marko\Debugbar\Support;

/**
 * Masks credentials before they reach the toolbar HTML or a stored profile.
 *
 * Keys are matched case-insensitively by substring, so `X-Api-Key`, `user_password`
 * and `Set-Cookie` are all caught. Cookie headers keep their cookie names but lose
 * every value, so a session ID never leaves the request that carried it.
 */
class Redactor
{
    public const string MASK = '[masked]';

    /** @var list<string> */
    private const array SENSITIVE_FRAGMENTS = [
        'authorization',
        'password',
        'token',
        'secret',
        'api-key',
        'api_key',
        'cookie',
    ];

    public function isSensitiveKey(
        string $key,
    ): bool {
        $key = strtolower($key);

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively mask every value whose key looks sensitive.
     *
     * @param array<mixed> $values
     * @return array<mixed>
     */
    public function redact(
        array $values,
    ): array {
        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $values[$key] = self::MASK;
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    /**
     * Mask a header value. Cookie headers keep cookie names and attributes; any other
     * sensitive header is masked entirely.
     */
    public function header(
        string $name,
        string $value,
    ): string {
        return match (strtolower($name)) {
            'cookie' => $this->cookieHeader($value),
            'set-cookie' => $this->setCookieHeader($value),
            default => $this->isSensitiveKey($name) ? self::MASK : $value,
        };
    }

    /**
     * Mask a raw `Name: value` header line as returned by headers_list().
     */
    public function headerLine(
        string $line,
    ): string {
        $separator = strpos($line, ':');

        if ($separator === false) {
            return $line;
        }

        $name = substr($line, 0, $separator);

        return $name.': '.$this->header($name, ltrim(substr($line, $separator + 1)));
    }

    /**
     * Mask sensitive query-string values inside a request URI, leaving everything else intact.
     */
    public function uri(
        string $uri,
    ): string {
        $queryStart = strpos($uri, '?');

        if ($queryStart === false) {
            return $uri;
        }

        $fragmentStart = strpos($uri, '#', $queryStart);
        $query = $fragmentStart === false
            ? substr($uri, $queryStart + 1)
            : substr($uri, $queryStart + 1, $fragmentStart - $queryStart - 1);
        $fragment = $fragmentStart === false ? '' : substr($uri, $fragmentStart);

        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            $parts = explode('=', $pair, 2);

            if (
                count($parts) === 2
                && $this->isSensitiveKey(urldecode($parts[0]))
            ) {
                $pair = $parts[0].'='.self::MASK;
            }

            $pairs[] = $pair;
        }

        return substr($uri, 0, $queryStart + 1).implode('&', $pairs).$fragment;
    }

    private function cookieHeader(
        string $value,
    ): string {
        $cookies = [];

        foreach (explode(';', $value) as $cookie) {
            $cookie = trim($cookie);

            if ($cookie === '') {
                continue;
            }

            $cookies[] = $this->maskCookiePair($cookie);
        }

        return implode('; ', $cookies);
    }

    private function setCookieHeader(
        string $value,
    ): string {
        $parts = explode(';', $value, 2);
        $masked = $this->maskCookiePair(trim($parts[0]));

        return isset($parts[1]) ? $masked.';'.$parts[1] : $masked;
    }

    private function maskCookiePair(
        string $pair,
    ): string {
        $separator = strpos($pair, '=');

        if ($separator === false) {
            return self::MASK;
        }

        return substr($pair, 0, $separator).'='.self::MASK;
    }
}
