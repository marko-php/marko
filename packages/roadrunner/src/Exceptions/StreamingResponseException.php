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
            message: "Cannot bridge a streamed response ($responseClass) to PSR-7 for RoadRunner.",
            context: 'The response implements StreamedResponseInterface (an SSE stream, a StreamedResponse or a FileResponse), so its body() is empty; the RoadRunner PSR-7 worker only sends a buffered body.',
            suggestion: 'Serve streamed and file responses through PHP-FPM or the dev server, or return a plain Response with the full body when running under RoadRunner.',
        );
    }
}
