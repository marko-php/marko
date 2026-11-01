<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class StreamingResponseException extends MarkoException
{
    public static function serverSentEvents(
        string $responseClass,
    ): self {
        return new self(
            message: "Cannot serve a server-sent events response ($responseClass) under RoadRunner.",
            context: 'The response declares Content-Type: text/event-stream. Server-sent events keep the connection open for its whole life, but the RoadRunner PSR-7 worker collects the full body before it answers and must then return to the accept loop, so the stream would never reach the client.',
            suggestion: 'Serve server-sent events routes through PHP-FPM or the dev server (e.g. a separate FPM pool behind the same proxy). File downloads and StreamedResponse bodies do work under RoadRunner.',
        );
    }
}
