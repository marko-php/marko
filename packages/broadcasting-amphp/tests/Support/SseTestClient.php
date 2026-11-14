<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Tests\Support;

use function Amp\Socket\connect;

use Amp\Socket\Socket;
use Amp\TimeoutCancellation;
use RuntimeException;

/**
 * Minimal raw HTTP/1.1 client for the integration tests: sends one GET and decodes the
 * (chunked) response body incrementally so tests can wait for individual SSE frames.
 */
class SseTestClient
{
    /**
     * Upper bound for every wait. Generous on purpose: a passing wait returns as soon as the
     * data arrives, so only a run that would fail anyway pays for it.
     */
    private const float WAIT_TIMEOUT = 5.0;

    public private(set) int $status = 0;

    /**
     * @var array<string, string>
     */
    public private(set) array $headers = [];

    public private(set) string $body = '';

    private string $raw = '';

    private bool $headersParsed = false;

    private bool $chunked = false;

    private bool $ended = false;

    private int $consumed = 0;

    private function __construct(
        private readonly Socket $socket,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public static function get(
        int $port,
        string $target,
        array $headers = [],
        bool $close = false,
    ): self {
        $socket = connect("tcp://127.0.0.1:$port");
        $lines = ["GET $target HTTP/1.1", 'Host: 127.0.0.1'];

        foreach ($headers as $name => $value) {
            $lines[] = "$name: $value";
        }

        if ($close) {
            $lines[] = 'Connection: close';
        }

        $socket->write(implode("\r\n", $lines) . "\r\n\r\n");
        $client = new self($socket);
        $client->waitForHeaders();

        return $client;
    }

    /**
     * Wait until the decoded body contains $needle; returns the body read so far.
     */
    public function waitFor(
        string $needle,
        float $timeout = self::WAIT_TIMEOUT,
    ): string {
        $cancellation = new TimeoutCancellation(
            $timeout,
            "Timed out waiting for '" . addcslashes($needle, "\n") . "'; body so far: " . addcslashes(
                $this->body,
                "\n",
            ),
        );

        while (!str_contains(substr($this->body, $this->consumed), $needle)) {
            if ($this->ended) {
                throw new RuntimeException(
                    "Stream ended before '" . addcslashes($needle, "\n") . "' arrived; body: " . addcslashes(
                        $this->body,
                        "\n",
                    ),
                );
            }

            $this->readMore($cancellation);
        }

        $position = strpos($this->body, $needle, $this->consumed);
        $this->consumed = (int) $position + strlen($needle);

        return $this->body;
    }

    /**
     * Read until the server ends the response or closes the socket.
     */
    public function waitForEnd(
        float $timeout = self::WAIT_TIMEOUT,
    ): string {
        $cancellation = new TimeoutCancellation($timeout, 'Timed out waiting for the response to end');

        while (!$this->ended) {
            $this->readMore($cancellation);
        }

        return $this->body;
    }

    public function close(): void
    {
        $this->socket->close();
    }

    private function waitForHeaders(): void
    {
        $cancellation = new TimeoutCancellation(self::WAIT_TIMEOUT, 'Timed out waiting for response headers');

        while (!$this->headersParsed) {
            $this->readMore($cancellation);
        }
    }

    private function readMore(
        TimeoutCancellation $cancellation,
    ): void {
        $chunk = $this->socket->read($cancellation);

        if ($chunk === null) {
            $this->ended = true;

            return;
        }

        $this->raw .= $chunk;
        $this->parse();
    }

    private function parse(): void
    {
        if (!$this->headersParsed) {
            $end = strpos($this->raw, "\r\n\r\n");

            if ($end === false) {
                return;
            }

            $lines = explode("\r\n", substr($this->raw, 0, $end));
            $this->status = (int) explode(' ', (string) array_shift($lines))[1];

            foreach ($lines as $line) {
                [$name, $value] = explode(':', $line, 2);
                $this->headers[strtolower(trim($name))] = trim($value);
            }

            $this->raw = substr($this->raw, $end + 4);
            $this->headersParsed = true;
            $this->chunked = ($this->headers['transfer-encoding'] ?? '') === 'chunked';
        }

        if (!$this->chunked) {
            $this->body .= $this->raw;
            $this->raw = '';

            if (
                isset($this->headers['content-length'])
                && strlen(
                    $this->body,
                ) >= (int) $this->headers['content-length']
            ) {
                $this->ended = true;
            }

            return;
        }

        while (($lineEnd = strpos($this->raw, "\r\n")) !== false) {
            $size = (int) hexdec(substr($this->raw, 0, $lineEnd));

            if ($size === 0) {
                $this->ended = true;
                $this->raw = '';

                return;
            }

            if (strlen($this->raw) < $lineEnd + 2 + $size + 2) {
                return;
            }

            $this->body .= substr($this->raw, $lineEnd + 2, $size);
            $this->raw = substr($this->raw, $lineEnd + 2 + $size + 2);
        }
    }
}
