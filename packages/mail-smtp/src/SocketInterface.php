<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

interface SocketInterface
{
    public function connect(
        string $host,
        int $port,
        ?string $encryption = null,
        int $timeout = 30,
    ): void;

    public function read(): string;

    public function write(string $data): void;

    public function enableTls(): bool;

    public function close(): void;

    public bool $connected {
        get;
    }
}
