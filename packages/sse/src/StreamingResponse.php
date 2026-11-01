<?php

declare(strict_types=1);

namespace Marko\Sse;

use JsonException;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Routing\Http\AbstractStreamedResponse;
use Marko\Routing\Http\Response;
use Override;
use Random\RandomException;

class StreamingResponse extends AbstractStreamedResponse
{
    /**
     * Extra seconds a connection slot outlives the stream timeout, covering the final poll interval
     * and shutdown. If the worker dies without releasing its slot, the slot frees itself after
     * stream timeout + this grace period.
     */
    public const int SLOT_TTL_GRACE_SECONDS = 60;

    public function __construct(
        private SseStream $stream,
        int $statusCode = 200,
        private ?SseConnectionLimiter $connectionLimiter = null,
    ) {
        parent::__construct(
            statusCode: $statusCode,
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * Takes a connection slot (when limited) before any header is sent, so a full
     * server can still answer 503; the stream itself is written by sendBody().
     *
     * @throws JsonException|InvalidKeyException|RandomException
     */
    #[Override]
    public function send(): void
    {
        if ($this->isBodyOmitted()) {
            // HEAD request: send the stream's headers, never open the stream.
            $this->stream->close();
            parent::send();

            return;
        }

        $slot = null;

        if ($this->connectionLimiter?->isLimited()) {
            $slot = $this->connectionLimiter->acquire($this->stream->timeout() + self::SLOT_TTL_GRACE_SECONDS);

            if ($slot === null) {
                $this->stream->close();
                $this->serviceUnavailableResponse()->send();

                return;
            }
        }

        try {
            parent::send();
        } finally {
            if ($slot !== null) {
                $this->connectionLimiter->release($slot);
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function sendBody(): void
    {
        $outputStreamer = $this->outputStreamer();

        try {
            $outputStreamer->prepare();

            foreach ($this->stream as $chunk) {
                if (!$outputStreamer->write($chunk)) {
                    break;
                }
            }
        } finally {
            $this->stream->close();
        }
    }

    /**
     * The response sent instead of the stream when every connection slot is taken.
     *
     * EventSource clients do not reconnect after a 503, so the page should retry
     * after the Retry-After delay (see the marko/sse docs).
     */
    public function serviceUnavailableResponse(): Response
    {
        return new Response(
            body: '',
            statusCode: 503,
            headers: [
                'Retry-After' => (string) ($this->connectionLimiter?->retryAfter() ?? 0),
                'Cache-Control' => 'no-cache',
            ],
        );
    }
}
