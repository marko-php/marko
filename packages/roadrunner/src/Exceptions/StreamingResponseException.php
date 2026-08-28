<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class StreamingResponseException extends MarkoException
{
    public static function unsupported(
        string $responseClass,
    ): self {
        return new self(
            message: "Cannot bridge a streaming response ($responseClass) to PSR-7 for RoadRunner.",
            context: 'The RoadRunner PSR-7 HTTP worker buffers the full response body and has no way to emit a live stream through this bridge.',
            suggestion: 'Do not return a streaming response from a route handler when serving requests through the RoadRunner driver.',
        );
    }
}
