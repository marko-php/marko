<?php

declare(strict_types=1);

namespace Marko\Testing\Http;

use Marko\Routing\Http\OutputStreamer;

/**
 * An OutputStreamer that leaves output buffers and the time limit alone, so a
 * streamed body can be captured with ob_start() inside a test.
 *
 * TestResponse::streamedContent() uses it; pass it to
 * StreamedResponseInterface::withOutputStreamer() to capture a body yourself.
 */
class BufferedOutputStreamer extends OutputStreamer
{
    public function prepare(): void {}

    public function connectionAborted(): bool
    {
        return false;
    }
}
