<?php

declare(strict_types=1);

namespace Marko\Routing\Signing;

use DateTimeImmutable;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\Exceptions\UrlSigningException;
use Marko\Routing\Http\Request;
use Marko\Routing\RoutingConfig;
use Marko\Routing\UrlGeneratorInterface;
use Psr\Clock\ClockInterface;

/**
 * HMAC-SHA256 URL signer keyed by `routing.signing.key`.
 *
 * The signature covers "scheme://host/path?query", where the host is
 * lowercased without its default port, each path segment is re-encoded and
 * the query is sorted by key with `signature` and the configured ignored
 * parameters removed. Signatures compare with hash_equals, against the
 * current key and then each of `routing.signing.previous_keys`.
 *
 * The request's scheme comes from the server's HTTPS / REQUEST_SCHEME
 * variables and its host from HTTP_HOST; forwarded headers are ignored.
 */
readonly class HmacUrlSigner implements UrlSignerInterface
{
    /**
     * Minimum signing key length in bytes (the HMAC-SHA256 block of entropy).
     */
    public const int MIN_KEY_LENGTH = 32;

    private const string SIGNATURE = 'signature';

    private const string EXPIRES = 'expires';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RoutingConfig $routingConfig,
        private ClockInterface $clock,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     * @throws UrlGenerationException|UrlSigningException|ConfigNotFoundException
     */
    public function signedRoute(
        string $name,
        array $parameters = [],
        ?DateTimeImmutable $expiresAt = null,
        bool $absolute = true,
    ): string {
        return $this->sign($this->urlGenerator->route($name, $parameters, $absolute), $expiresAt);
    }

    /**
     * @throws UrlSigningException|ConfigNotFoundException
     */
    public function sign(
        string $url,
        ?DateTimeImmutable $expiresAt = null,
    ): string {
        $key = $this->signingKeys()[0];
        $parts = parse_url($url);

        if ($parts === false || (isset($parts['host']) && !isset($parts['scheme'])) || !isset($parts['path'])) {
            throw UrlSigningException::unparseableUrl($url);
        }

        if (isset($parts['fragment'])) {
            throw UrlSigningException::fragment($url);
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        foreach ([self::SIGNATURE, self::EXPIRES] as $reserved) {
            if (array_key_exists($reserved, $query)) {
                throw UrlSigningException::reservedParameter($url, $reserved);
            }
        }

        if ($expiresAt !== null) {
            $expires = (string) $expiresAt->getTimestamp();
            $url = $this->appendQuery($url, self::EXPIRES . '=' . $expires);
            $query[self::EXPIRES] = $expires;
        }

        [$scheme, $host] = isset($parts['host'])
            ? [$parts['scheme'] ?? '', $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')]
            : $this->baseOrigin($url);

        $signature = hash_hmac('sha256', $this->payload($scheme, $host, $parts['path'], $query), $key);

        return $this->appendQuery($url, self::SIGNATURE . '=' . $signature);
    }

    /**
     * @throws UrlSigningException|ConfigNotFoundException
     */
    public function hasValidSignature(
        Request $request,
    ): bool {
        try {
            $this->assertValidSignature($request);
        } catch (InvalidSignatureException) {
            return false;
        }

        return true;
    }

    /**
     * @throws InvalidSignatureException|UrlSigningException|ConfigNotFoundException
     */
    public function assertValidSignature(
        Request $request,
    ): void {
        $keys = $this->signingKeys();
        $query = $request->query();
        $signature = $query[self::SIGNATURE] ?? null;
        $expires = $query[self::EXPIRES] ?? null;

        if (!is_string($signature) || $signature === '') {
            throw InvalidSignatureException::invalid();
        }

        if ($expires !== null && (!is_string($expires) || !ctype_digit($expires))) {
            throw InvalidSignatureException::invalid();
        }

        $payload = $this->payload(
            $this->requestScheme($request),
            $request->server('HTTP_HOST') ?? $request->server('SERVER_NAME') ?? '',
            $request->path(),
            $query,
        );

        if (!$this->matchesAnyKey($payload, $signature, $keys)) {
            throw InvalidSignatureException::invalid();
        }

        if ($expires !== null && $this->clock->now()->getTimestamp() >= (int) $expires) {
            throw InvalidSignatureException::expired();
        }
    }

    /**
     * The current key first, then the previous keys, each validated.
     *
     * @return non-empty-list<string>
     * @throws UrlSigningException|ConfigNotFoundException
     */
    private function signingKeys(): array
    {
        $key = $this->routingConfig->signingKey();

        if ($key === '') {
            throw UrlSigningException::missingKey();
        }

        $this->assertKeyLength($key, 'routing.signing.key');
        $keys = [$key];

        foreach ($this->routingConfig->signingPreviousKeys() as $previousKey) {
            if (!is_string($previousKey) || $previousKey === '') {
                throw UrlSigningException::invalidPreviousKey();
            }

            $this->assertKeyLength($previousKey, 'routing.signing.previous_keys');
            $keys[] = $previousKey;
        }

        return $keys;
    }

    /**
     * @throws UrlSigningException
     */
    private function assertKeyLength(
        string $key,
        string $configKey,
    ): void {
        if (strlen($key) < self::MIN_KEY_LENGTH) {
            throw UrlSigningException::keyTooShort($configKey, strlen($key), self::MIN_KEY_LENGTH);
        }
    }

    /**
     * @param list<string> $keys
     */
    private function matchesAnyKey(
        string $payload,
        string $signature,
        array $keys,
    ): bool {
        $matched = false;

        foreach ($keys as $key) {
            $matched = hash_equals(hash_hmac('sha256', $payload, $key), $signature) || $matched;
        }

        return $matched;
    }

    /**
     * The scheme and host of `routing.url`, used to sign relative URLs.
     *
     * @return array{string, string}
     * @throws UrlSigningException|ConfigNotFoundException
     */
    private function baseOrigin(
        string $url,
    ): array {
        $base = parse_url($this->routingConfig->url());

        if ($base === false || !isset($base['scheme'], $base['host'])) {
            throw UrlSigningException::missingBaseUrl($url);
        }

        return [$base['scheme'], $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '')];
    }

    /**
     * @param array<mixed> $query
     * @throws ConfigNotFoundException
     */
    private function payload(
        string $scheme,
        string $host,
        string $path,
        array $query,
    ): string {
        $scheme = strtolower($scheme);
        unset($query[self::SIGNATURE]);

        foreach ($this->routingConfig->signingIgnoredParameters() as $ignored) {
            unset($query[$ignored]);
        }

        $this->sortRecursive($query);

        return $scheme . '://' . $this->canonicalHost($host, $scheme) . $this->canonicalPath($path)
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function canonicalHost(
        string $host,
        string $scheme,
    ): string {
        $host = strtolower(trim($host));
        $defaultPort = match ($scheme) {
            'https' => ':443',
            'http' => ':80',
            default => null,
        };

        if ($defaultPort !== null && str_ends_with($host, $defaultPort)) {
            $host = substr($host, 0, -strlen($defaultPort));
        }

        return rtrim($host, '.');
    }

    private function canonicalPath(
        string $path,
    ): string {
        if ($path === '') {
            return '/';
        }

        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', $path),
        ));
    }

    /**
     * @param array<mixed> $values
     */
    private function sortRecursive(
        array &$values,
    ): void {
        ksort($values, SORT_STRING);

        foreach ($values as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }

    private function requestScheme(
        Request $request,
    ): string {
        $https = $request->server('HTTPS');

        if ($https !== null && $https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }

        return strtolower($request->server('REQUEST_SCHEME') ?? '') === 'https' ? 'https' : 'http';
    }

    private function appendQuery(
        string $url,
        string $pair,
    ): string {
        return $url . (str_contains($url, '?') ? '&' : '?') . $pair;
    }
}
