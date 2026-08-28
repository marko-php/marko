<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Http;

use Marko\Roadrunner\Exceptions\StreamingResponseException;
use Marko\Routing\Http\Response;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;

readonly class Psr7ResponseBridge
{
    private const string DEFAULT_STREAMING_RESPONSE_CLASS = 'Marko\Sse\StreamingResponse';

    public function __construct(
        private string $streamingResponseClass = self::DEFAULT_STREAMING_RESPONSE_CLASS,
    ) {}

    /**
     * @throws StreamingResponseException
     */
    public function bridge(Response $response): ResponseInterface
    {
        if ($this->isStreamingResponse($response)) {
            throw StreamingResponseException::unsupported($response::class);
        }

        $psr7Response = new Psr7Response(
            status: $response->statusCode(),
            body: $response->body(),
        );

        foreach ($response->headerLines() as $line) {
            [$name, $value] = explode(': ', $line, 2);

            $psr7Response = $name === 'Set-Cookie'
                ? $psr7Response->withAddedHeader($name, $value)
                : $psr7Response->withHeader($name, $value);
        }

        return $psr7Response;
    }

    private function isStreamingResponse(Response $response): bool
    {
        return class_exists($this->streamingResponseClass)
            && is_a($response, $this->streamingResponseClass);
    }
}
