<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ContentDispositionException extends MarkoException
{
    public static function invalidFilename(
        string $filename,
    ): self {
        $printable = addcslashes($filename, "\0..\37");

        return new self(
            message: "Invalid Content-Disposition filename '$printable'",
            context: 'While building a Content-Disposition header',
            suggestion: 'Pass a bare, non-empty filename: no CR, LF, NUL, or / and \\ path separators',
        );
    }

    public static function malformedHeader(
        string $value,
    ): self {
        return new self(
            message: "Malformed Content-Disposition header '$value'",
            context: 'While parsing a Content-Disposition header',
            suggestion: "Expect 'inline' or 'attachment', optionally followed by filename=\"...\" and/or filename*=UTF-8''...",
        );
    }
}
