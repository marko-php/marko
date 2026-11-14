<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Core\Support\ErrorCapture;
use Marko\Mail\Exception\TransportException;

class StreamSocket implements SocketInterface
{
    /** @var resource|null */
    protected mixed $stream = null;

    protected ?string $host = null;

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
        // 'tls' means STARTTLS: connect in plain text, SmtpMailerFactory upgrades the connection afterwards
        $transport = match ($encryption) {
            'ssl' => 'ssl',
            default => 'tcp',
        };

        $address = "$transport://$host:$port";
        $errno = 0;
        $errstr = '';

        // stream_socket_client() reports the OS reason both through $errstr and as a
        // PHP warning; capture the warning so it is not emitted, and fall back to it
        // when $errstr is empty (e.g. a failure before the socket is created).
        $stream = ErrorCapture::run(
            $reason,
            function () use ($address, $timeout, &$errno, &$errstr): mixed {
                return stream_socket_client(
                    address: $address,
                    error_code: $errno,
                    error_message: $errstr,
                    timeout: $timeout,
                );
            },
        );

        if ($stream === false) {
            throw TransportException::connectionFailed($host, $port, $errstr !== '' ? $errstr : $reason);
        }

        $this->stream = $stream;
        $this->host = $host;
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
            if (
                strlen($line) >= 4
                && $line[3] === ' '
            ) {
                break;
            }

            // Single-line response (no dash at position 3)
            if (strlen($line) < 4) {
                break;
            }
        }

        return rtrim($response, "\r\n");
    }

    public function write(
        string $data,
    ): void {
        if ($this->stream === null) {
            return;
        }

        fwrite($this->stream, $data);
    }

    /**
     * Returns false when there is no open connection.
     *
     * @throws TransportException When the TLS handshake fails, with the OpenSSL reason
     */
    public function enableTls(): bool
    {
        if ($this->stream === null) {
            return false;
        }

        $result = ErrorCapture::run(
            $reason,
            fn (): bool|int => stream_socket_enable_crypto(
                stream: $this->stream,
                enable: true,
                crypto_method: STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ),
        );

        if ($result !== true) {
            throw TransportException::tlsFailed($this->host ?? 'unknown host', $reason);
        }

        return true;
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
            $this->host = null;
        }
    }
}
