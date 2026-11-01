<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

/**
 * The request's Range header is malformed or lies outside the file.
 * Rendered as 416 Range Not Satisfiable, with a Content-Range header that
 * carries the file size (an asterisk, a slash, then the size).
 */
class RangeNotSatisfiableException extends HttpException
{
    public static function forRange(
        string $range,
        int $size,
    ): self {
        return new self(
            statusCode: 416,
            headers: ['Content-Range' => "bytes */$size"],
            context: "While parsing the Range header '$range' for a $size-byte file",
            suggestion: 'Send a single byte range inside the file, e.g. bytes=0-1023 or bytes=-500',
        );
    }
}
