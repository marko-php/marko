<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Mail\Exception\TransportException;

class StreamSocket implements SocketInterface
{
    /** @var resource|null */
    protected mixed $stream = null;

    public bool $connected {
        get => $this->stream !== null;
    }

    /**
     * @throws TransportException
     */
    public function connect(
        string $host,
        int $port,
        ?string $encryption = null,
        int $timeout = 30,
    ): void {
        $transport = match ($encryption) {
            'ssl', 'tls' => 'ssl',
            default => 'tcp',
        };

        $address = "$transport://$host:$port";
        $errno = 0;
        $errstr = '';

        $stream = @stream_socket_client(
            address: $address,
            error_code: $errno,
            error_message: $errstr,
            timeout: $timeout,
        );

        if ($stream === false) {
            throw TransportException::connectionFailed($host, $port);
        }

        $this->stream = $stream;
    }

    public function read(): string
    {
        if ($this->stream === null) {
            return '';
        }

        $response = '';

        while (true) {
            $line = fgets($this->stream);

            if ($line === false) {
                break;
            }

            $response .= $line;

            // SMTP multi-line: "250-..." continues, "250 ..." terminates
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }

            // Single-line response (no dash at position 3)
            if (strlen($line) < 4) {
                break;
            }
        }

        return rtrim($response, "\r\n");
    }

    public function write(string $data): void
    {
        if ($this->stream === null) {
            return;
        }

        fwrite($this->stream, $data);
    }

    /**
     * @throws TransportException
     */
    public function enableTls(): bool
    {
        if ($this->stream === null) {
            return false;
        }

        $result = stream_socket_enable_crypto(
            socket: $this->stream,
            enable: true,
            crypto_method: STREAM_CRYPTO_METHOD_TLS_CLIENT,
        );

        return $result === true;
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }
}
