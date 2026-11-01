<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Closure;

/**
 * A response whose body is generated while it is sent, e.g. a CSV export.
 *
 * The callback receives the OutputStreamer and writes chunks with
 * `$outputStreamer->write($chunk)`; stop when write() returns false (the
 * client disconnected). The callback is never called for HEAD requests.
 */
class StreamedResponse extends AbstractStreamedResponse
{
    private Closure $callback;

    /**
     * @param callable(OutputStreamer): void $callback
     * @param array<string, string> $headers
     */
    public function __construct(
        callable $callback,
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct(statusCode: $statusCode, headers: $headers);

        $this->callback = $callback(...);
    }

    public function sendBody(): void
    {
        $outputStreamer = $this->outputStreamer();
        $outputStreamer->prepare();

        ($this->callback)($outputStreamer);
    }
}
