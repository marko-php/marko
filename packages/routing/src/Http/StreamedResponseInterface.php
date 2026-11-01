<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

/**
 * A response that writes its body straight to output instead of holding it in body().
 *
 * body() returns '' for every implementer, so runtimes and middleware that
 * read body() (PSR-7 bridges, page caches) check for this interface instead
 * of guessing from the class name. Response::send() emits the headers, then
 * calls sendBody() unless the body was omitted (HEAD).
 */
interface StreamedResponseInterface
{
    /**
     * Write the body to output, chunk by chunk, through the response's OutputStreamer.
     */
    public function sendBody(): void;

    /**
     * A copy of this response that writes its body through the given streamer.
     *
     * Tests use it to capture the body in an output buffer (the default
     * streamer drains every buffer before it writes).
     */
    public function withOutputStreamer(OutputStreamer $outputStreamer): static;
}
