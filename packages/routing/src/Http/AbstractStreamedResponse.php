<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use NoDiscard;

/**
 * Base for responses that stream their body: body() is always '', and the
 * body is written by sendBody() through the response's OutputStreamer.
 */
abstract class AbstractStreamedResponse extends Response implements StreamedResponseInterface
{
    private OutputStreamer $outputStreamer;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct(body: '', statusCode: $statusCode, headers: $headers);

        $this->outputStreamer = new OutputStreamer();
    }

    #[NoDiscard]
    public function withOutputStreamer(OutputStreamer $outputStreamer): static
    {
        $clone = clone $this;
        $clone->outputStreamer = $outputStreamer;

        return $clone;
    }

    protected function outputStreamer(): OutputStreamer
    {
        return $this->outputStreamer;
    }
}
