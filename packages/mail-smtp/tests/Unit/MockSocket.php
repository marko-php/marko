<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Unit;

use Marko\Mail\Smtp\SocketInterface;

class MockSocket implements SocketInterface
{
    public private(set) bool $connected = false;

    public private(set) bool $tlsEnabled = false;

    private int $responseIndex = 0;

    /** @var array<string> */
    public private(set) array $written = [];

    public private(set) string $host = '';

    public function __construct(
        private readonly array $responses,
        private readonly bool $tlsSuccess = true,
    ) {}

    public function connect(
        string $host,
        int $port,
        ?string $encryption = null,
        int $timeout = 30,
    ): void {
        $this->host = $host;
        $this->connected = true;
    }

    public function read(): string
    {
        return $this->responses[$this->responseIndex++] ?? '';
    }

    public function write(
        string $data,
    ): void {
        $this->written[] = $data;
    }

    public function enableTls(): bool
    {
        if ($this->tlsSuccess) {
            $this->tlsEnabled = true;

            return true;
        }

        return false;
    }

    public function close(): void
    {
        $this->connected = false;
    }
}
