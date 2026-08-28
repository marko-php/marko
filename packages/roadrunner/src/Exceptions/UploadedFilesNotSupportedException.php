<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class UploadedFilesNotSupportedException extends MarkoException
{
    public static function whenBridgingRequest(): self
    {
        return new self(
            message: 'Cannot bridge a PSR-7 request carrying uploaded files to a Marko Request.',
            context: 'Marko\Routing\Http\Request has no equivalent of $_FILES, so PSR-7 uploaded files have nowhere to map.',
            suggestion: 'Do not submit multipart/form-data uploads to a route served through the RoadRunner driver. See https://marko.build/docs/packages/roadrunner/',
        );
    }
}
