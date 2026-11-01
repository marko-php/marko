<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Routing\Exceptions\RangeNotSatisfiableException;

/**
 * One satisfiable byte range of a file, parsed from an HTTP Range header.
 *
 * Supports `bytes=start-end`, `bytes=start-` and the suffix form
 * `bytes=-length`. A request for several ranges (`bytes=0-1,5-9`) is not
 * served as multipart/byteranges: fromHeader() returns null and the whole
 * file is sent with 200.
 */
readonly class ByteRange
{
    private const string PATTERN = '/^bytes=(\d*)-(\d*)$/i';

    /**
     * @param int $start First byte, zero-based
     * @param int $end Last byte, inclusive
     */
    public function __construct(
        private int $start,
        private int $end,
    ) {}

    /**
     * @return self|null null when there is no Range header, the unit is not bytes, or several ranges were requested
     * @throws RangeNotSatisfiableException When the range is malformed or lies outside the file
     */
    public static function fromHeader(
        ?string $range,
        int $size,
    ): ?self {
        if ($range === null || trim($range) === '') {
            return null;
        }

        $range = trim($range);

        if (!str_starts_with(strtolower($range), 'bytes=')) {
            return null;
        }

        if (str_contains($range, ',')) {
            return null;
        }

        if (preg_match(self::PATTERN, str_replace(' ', '', $range), $matches) !== 1) {
            throw RangeNotSatisfiableException::forRange($range, $size);
        }

        [, $first, $last] = $matches;

        if ($first === '' && $last === '') {
            throw RangeNotSatisfiableException::forRange($range, $size);
        }

        if ($first === '') {
            $suffixLength = (int) $last;

            if ($suffixLength === 0 || $size === 0) {
                throw RangeNotSatisfiableException::forRange($range, $size);
            }

            return new self(start: max(0, $size - $suffixLength), end: $size - 1);
        }

        $start = (int) $first;
        $end = $last === '' ? $size - 1 : min((int) $last, $size - 1);

        if ($start >= $size || $start > $end) {
            throw RangeNotSatisfiableException::forRange($range, $size);
        }

        return new self(start: $start, end: $end);
    }

    public function start(): int
    {
        return $this->start;
    }

    public function end(): int
    {
        return $this->end;
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    /**
     * The Content-Range header value for a 206 response, e.g. `bytes 0-499/1234`.
     */
    public function contentRange(
        int $size,
    ): string {
        return "bytes $this->start-$this->end/$size";
    }
}
