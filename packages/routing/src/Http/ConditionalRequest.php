<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

/**
 * The validator headers of a request (If-None-Match, If-Modified-Since,
 * If-Range), evaluated against a file's ETag and modification time.
 *
 * Follows RFC 9110: If-None-Match wins over If-Modified-Since and compares
 * weakly; If-Range compares strongly, so a weak ETag never matches it and the
 * client gets the full file instead of a range. An unparseable date is
 * ignored, as the RFC requires.
 */
readonly class ConditionalRequest
{
    public function __construct(
        private ?string $ifNoneMatch = null,
        private ?string $ifModifiedSince = null,
        private ?string $ifRange = null,
    ) {}

    public static function fromRequest(
        Request $request,
    ): self {
        return new self(
            ifNoneMatch: $request->header('If-None-Match'),
            ifModifiedSince: $request->header('If-Modified-Since'),
            ifRange: $request->header('If-Range'),
        );
    }

    /**
     * A weak ETag built from the file size and modification time, e.g. `W/"4d2-65f1a2b3"`.
     */
    public static function etagFor(
        int $size,
        int $lastModified,
    ): string {
        return sprintf('W/"%x-%x"', $size, $lastModified);
    }

    /**
     * The Last-Modified header value (an IMF-fixdate) for a Unix timestamp.
     */
    public static function httpDate(
        int $timestamp,
    ): string {
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }

    /**
     * True when the client's cached copy is current and a 304 Not Modified should be sent.
     */
    public function isNotModified(
        string $etag,
        int $lastModified,
    ): bool {
        if ($this->ifNoneMatch !== null) {
            return $this->matchesWeakly($this->ifNoneMatch, $etag);
        }

        if ($this->ifModifiedSince === null) {
            return false;
        }

        $since = strtotime($this->ifModifiedSince);

        return $since !== false && $lastModified <= $since;
    }

    /**
     * True when a Range header may be honoured: there is no If-Range, or it still matches the file.
     */
    public function allowsRange(
        string $etag,
        int $lastModified,
    ): bool {
        if ($this->ifRange === null) {
            return true;
        }

        $ifRange = trim($this->ifRange);

        if (str_starts_with($ifRange, '"') || str_starts_with($ifRange, 'W/')) {
            return !str_starts_with($ifRange, 'W/')
                && !str_starts_with($etag, 'W/')
                && $ifRange === $etag;
        }

        $date = strtotime($ifRange);

        return $date !== false && $date === $lastModified;
    }

    private function matchesWeakly(
        string $header,
        string $etag,
    ): bool {
        if (trim($header) === '*') {
            return true;
        }

        $opaque = $this->opaqueTag($etag);

        return array_any(
            explode(',', $header),
            fn (string $candidate): bool => $this->opaqueTag(trim($candidate)) === $opaque,
        );
    }

    private function opaqueTag(
        string $etag,
    ): string {
        return str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
    }
}
