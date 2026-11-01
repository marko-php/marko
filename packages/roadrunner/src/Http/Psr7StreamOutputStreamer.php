<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Http;

use Marko\Routing\Http\OutputStreamer;
use Psr\Http\Message\StreamInterface;

/**
 * An OutputStreamer that writes each chunk into a PSR-7 stream instead of echoing it.
 *
 * Psr7ResponseBridge hands it to a StreamedResponseInterface so the body a
 * FileResponse or StreamedResponse would write to output becomes the PSR-7
 * body RoadRunner sends. prepare() leaves output buffers and the time limit
 * alone (the worker owns both), and the client is never seen as disconnected
 * while the body is being collected.
 */
class Psr7StreamOutputStreamer extends OutputStreamer
{
    public function __construct(
        private readonly StreamInterface $stream,
    ) {}

    public function prepare(): void {}

    public function write(
        string $chunk,
    ): bool {
        $this->stream->write($chunk);

        return true;
    }

    public function connectionAborted(): bool
    {
        return false;
    }
}
