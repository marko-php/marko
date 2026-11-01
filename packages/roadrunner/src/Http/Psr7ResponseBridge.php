<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Http;

use Marko\Roadrunner\Exceptions\StreamingResponseException;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponseInterface;
use Nyholm\Psr7\Response as Psr7Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Converts a framework Response into the PSR-7 response RoadRunner sends.
 *
 * A StreamedResponseInterface (FileResponse, StreamedResponse) has an empty
 * body(), so its sendBody() runs against a Psr7StreamOutputStreamer that
 * collects the body into a php://temp stream (in memory up to 2 MB, then on
 * disk). A FileResponse keeps the status and headers it computed, so 206
 * ranges, 304s and offload headers pass through unchanged. Server-sent events
 * cannot work through a buffered worker and are rejected.
 */
readonly class Psr7ResponseBridge
{
    private const string EVENT_STREAM_CONTENT_TYPE = 'text/event-stream';

    /**
     * @throws StreamingResponseException When the response is a server-sent events stream (Content-Type: text/event-stream)
     */
    public function bridge(Response $response): ResponseInterface
    {
        $psr7Response = new Psr7Response(
            status: $response->statusCode(),
            body: $response instanceof StreamedResponseInterface
                ? $this->streamedBody($response)
                : $response->body(),
        );

        foreach ($response->headerLines() as $line) {
            [$name, $value] = explode(': ', $line, 2);

            $psr7Response = $name === 'Set-Cookie'
                ? $psr7Response->withAddedHeader($name, $value)
                : $psr7Response->withHeader($name, $value);
        }

        return $psr7Response;
    }

    /**
     * @throws StreamingResponseException
     */
    private function streamedBody(
        Response&StreamedResponseInterface $response,
    ): StreamInterface {
        if ($this->isEventStream($response)) {
            throw StreamingResponseException::serverSentEvents($response::class);
        }

        $stream = Stream::create();

        if (!$response->isBodyOmitted()) {
            $response->withOutputStreamer(new Psr7StreamOutputStreamer($stream))->sendBody();
            $stream->rewind();
        }

        return $stream;
    }

    private function isEventStream(
        Response $response,
    ): bool {
        foreach ($response->headers() as $name => $value) {
            if (strcasecmp($name, 'Content-Type') === 0
                && stripos(ltrim($value), self::EVENT_STREAM_CONTENT_TYPE) === 0) {
                return true;
            }
        }

        return false;
    }
}
