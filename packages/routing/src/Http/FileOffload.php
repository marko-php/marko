<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Routing\Exceptions\FileResponseException;

/**
 * Hands a local file to the web server (X-Sendfile or X-Accel-Redirect)
 * instead of streaming it from PHP. Built from routing.file_offload.
 *
 * The prefix map rewrites a local path into what the server expects: the
 * longest matching local prefix is replaced by its value, e.g.
 * `/var/www/storage/videos/` => `/protected-videos/` for an nginx internal
 * location. With an empty map the local path is sent as is (X-Sendfile).
 */
readonly class FileOffload
{
    /**
     * @var list<string>
     */
    public const array SUPPORTED_HEADERS = ['X-Sendfile', 'X-Accel-Redirect'];

    /**
     * @param array<string, string> $prefixMap Local path prefix => server path prefix
     * @throws FileResponseException When the header is unsupported, or X-Accel-Redirect has no prefix map
     */
    public function __construct(
        private string $header,
        private array $prefixMap = [],
    ) {
        if (!in_array($header, self::SUPPORTED_HEADERS, true)) {
            throw FileResponseException::invalidOffloadHeader($header);
        }

        if ($header === 'X-Accel-Redirect' && $prefixMap === []) {
            throw FileResponseException::missingOffloadPrefixMap();
        }
    }

    public function header(): string
    {
        return $this->header;
    }

    /**
     * The offload header value for a local file path.
     *
     * @throws FileResponseException When a prefix map is configured and no prefix matches the path
     */
    public function headerValueFor(
        string $path,
    ): string {
        if ($this->prefixMap === []) {
            return $path;
        }

        $prefixes = array_filter(
            array_keys($this->prefixMap),
            fn (string $prefix): bool => $prefix !== '' && str_starts_with($path, $prefix),
        );

        if ($prefixes === []) {
            throw FileResponseException::offloadPathNotMapped($path);
        }

        usort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $prefix = $prefixes[0];

        return $this->prefixMap[$prefix] . substr($path, strlen($prefix));
    }
}
