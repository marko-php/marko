<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Support;

use RuntimeException;

/**
 * A minimal, dependency-free HTTP/1.1 client for driving real requests at a
 * real `rr serve` process over a raw TCP socket.
 *
 * The end-to-end suite's isolation assertions depend on controlling the
 * `Cookie` header exactly — including sending none at all for an anonymous
 * request — which a cookie-jar-based client would make awkward to express.
 */
readonly class RoadRunnerHttpClient
{
    private const int TIMEOUT_SECONDS = 5;

    private const int READ_CHUNK_BYTES = 8192;

    public function __construct(
        private string $host,
        private int $port,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function get(
        string $path,
        ?string $cookie = null,
    ): RoadRunnerHttpResponse {
        return $this->send('GET', $path, $cookie);
    }

    /**
     * @param array<string, string> $formFields
     *
     * @throws RuntimeException
     */
    public function post(
        string $path,
        array $formFields,
        ?string $cookie = null,
    ): RoadRunnerHttpResponse {
        return $this->send('POST', $path, $cookie, http_build_query($formFields));
    }

    /**
     * @throws RuntimeException
     */
    private function send(
        string $method,
        string $path,
        ?string $cookie,
        ?string $body = null,
    ): RoadRunnerHttpResponse {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, self::TIMEOUT_SECONDS);

        if ($socket === false) {
            throw new RuntimeException("Unable to connect to $this->host:$this->port: $errstr");
        }

        fwrite($socket, $this->buildRequest($method, $path, $cookie, $body));

        $raw = '';
        while (!feof($socket)) {
            $raw .= fread($socket, self::READ_CHUNK_BYTES);
        }

        fclose($socket);

        return $this->parseResponse($raw);
    }

    private function buildRequest(
        string $method,
        string $path,
        ?string $cookie,
        ?string $body,
    ): string {
        $headers = [
            "$method $path HTTP/1.1",
            "Host: $this->host",
            'Connection: close',
        ];

        if ($cookie !== null) {
            $headers[] = "Cookie: $cookie";
        }

        if ($body !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . ($body ?? '');
    }

    private function parseResponse(
        string $raw,
    ): RoadRunnerHttpResponse {
        $separatorPosition = strpos($raw, "\r\n\r\n");
        $rawHeaders = $separatorPosition === false ? $raw : substr($raw, 0, $separatorPosition);
        $rawBody = $separatorPosition === false ? '' : substr($raw, $separatorPosition + 4);

        $headerLines = explode("\r\n", $rawHeaders);
        $statusLine = array_shift($headerLines) ?? '';
        preg_match('#^HTTP/\d\.\d\s+(\d+)#', $statusLine, $matches);

        $chunked = array_any(
            $headerLines,
            static fn (string $line): bool => stripos($line, 'Transfer-Encoding:') === 0
                && stripos($line, 'chunked') !== false,
        );

        // Reversed so the first match is the last Set-Cookie in wire order:
        // when a response carries several, the last one wins.
        $setCookieLine = array_find(
            array_reverse($headerLines),
            static fn (string $line): bool => stripos($line, 'Set-Cookie:') === 0,
        );

        return new RoadRunnerHttpResponse(
            statusCode: isset($matches[1]) ? (int) $matches[1] : 0,
            body: $chunked ? $this->decodeChunkedBody($rawBody) : $rawBody,
            setCookie: $setCookieLine === null
                ? null
                : trim(substr($setCookieLine, strlen('Set-Cookie:'))),
        );
    }

    private function decodeChunkedBody(
        string $body,
    ): string {
        $decoded = '';
        $offset = 0;

        while (($lineEnd = strpos($body, "\r\n", $offset)) !== false) {
            $chunkSize = hexdec(trim(explode(';', substr($body, $offset, $lineEnd - $offset))[0]));

            if ($chunkSize <= 0) {
                break;
            }

            $decoded .= substr($body, $lineEnd + 2, $chunkSize);
            $offset = $lineEnd + 2 + $chunkSize + 2;
        }

        return $decoded;
    }
}
