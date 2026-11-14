<?php

declare(strict_types=1);

namespace Marko\PageCache;

use Marko\Routing\Http\Request;

readonly class CacheKey
{
    public function __construct(
        public string $method,
        public string $scheme,
        public string $host,
        public string $path,
        public string $query,
    ) {}

    /**
     * Build the key for a request. Only the query parameters named in $queryParams form part of the
     * key; every other parameter is ignored, so arbitrary query strings cannot mint new entries.
     *
     * @param array<string> $queryParams Allowlisted query parameter names (#[Cacheable(query: [...])])
     */
    public static function fromRequest(
        Request $request,
        array $queryParams,
    ): self {
        $scheme = self::schemeFromRequest($request);

        return new self(
            method: $request->method(),
            scheme: $scheme,
            host: self::normalizeHost(self::rawHostFromRequest($request), $scheme),
            path: $request->path(),
            query: self::buildQuery(array_intersect_key($request->query(), array_flip($queryParams))),
        );
    }

    /**
     * The request scheme, from the server's own HTTPS / REQUEST_SCHEME variables.
     *
     * Forwarded headers (X-Forwarded-Proto) are client-controlled and deliberately ignored.
     */
    public static function schemeFromRequest(
        Request $request,
    ): string {
        $https = $request->server('HTTPS');

        if (
            $https !== null
            && $https !== ''
            && strtolower($https) !== 'off'
        ) {
            return 'https';
        }

        $requestScheme = strtolower($request->server('REQUEST_SCHEME') ?? '');

        return $requestScheme === 'https' ? 'https' : 'http';
    }

    /**
     * The request host name without its port, lowercased; an empty string when the request carries none.
     */
    public static function hostnameFromRequest(
        Request $request,
    ): string {
        $host = self::normalizeHost(self::rawHostFromRequest($request), 'http');

        return preg_replace('/:\d+$/', '', $host) ?? $host;
    }

    /**
     * Lowercase a host and drop the port when it is the scheme's default (80 for http, 443 for https).
     */
    public static function normalizeHost(
        string $host,
        string $scheme,
    ): string {
        $host = rtrim(strtolower(trim($host)), '.');
        $defaultPort = $scheme === 'https' ? ':443' : ':80';

        if (str_ends_with($host, $defaultPort)) {
            return substr($host, 0, -strlen($defaultPort));
        }

        return $host;
    }

    public static function normalizeQuery(
        string $rawQuery,
    ): string {
        if ($rawQuery === '') {
            return '';
        }

        parse_str($rawQuery, $queryArray);

        return self::buildQuery($queryArray);
    }

    private static function rawHostFromRequest(
        Request $request,
    ): string {
        return $request->server('HTTP_HOST') ?? $request->server('SERVER_NAME') ?? '';
    }

    /**
     * @param array<string, mixed> $queryArray
     */
    private static function buildQuery(
        array $queryArray,
    ): string {
        ksort($queryArray);

        return http_build_query($queryArray, '', '&', PHP_QUERY_RFC3986);
    }

    public function hash(): string
    {
        return hash('xxh128', "$this->method|$this->scheme://$this->host|$this->path|$this->query");
    }
}
