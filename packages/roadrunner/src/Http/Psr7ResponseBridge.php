<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Http;

use Marko\Roadrunner\Exceptions\StreamingResponseException;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponseInterface;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;

readonly class Psr7ResponseBridge
{
    /**
     * @throws StreamingResponseException When the response streams its body (StreamedResponseInterface)
     */
    public function bridge(Response $response): ResponseInterface
    {
        if ($response instanceof StreamedResponseInterface) {
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
}
