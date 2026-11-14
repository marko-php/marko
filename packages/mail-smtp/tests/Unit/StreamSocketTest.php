<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Unit;

use Marko\Mail\Exception\TransportException;
use Marko\Mail\Smtp\SocketInterface;
use Marko\Mail\Smtp\StreamSocket;

/**
 * Test helper: StreamSocket with an injectable stream for unit testing read/write.
 */
class TestableStreamSocket extends StreamSocket
{
    /** @param resource $stream */
    public function injectStream(
        mixed $stream,
    ): void {
        $this->stream = $stream;
    }
}

it('implements SocketInterface and reports not connected before connect', function (): void {
    $socket = new StreamSocket();

    expect($socket)->toBeInstanceOf(SocketInterface::class)
        ->and($socket->connected)->toBeFalse();
});

it('reads a single CRLF-terminated reply line from the stream', function (): void {
    $socket = new TestableStreamSocket();
    $mem = fopen('php://memory', 'r+');
    fwrite($mem, "220 smtp.example.com ESMTP ready\r\n");
    rewind($mem);
    $socket->injectStream($mem);

    $result = $socket->read();

    expect($result)->toBe('220 smtp.example.com ESMTP ready');
});

it('accumulates a multi-line SMTP reply (250- continuations) into one read result', function (): void {
    $socket = new TestableStreamSocket();
    $mem = fopen('php://memory', 'r+');
    fwrite($mem, "250-smtp.example.com\r\n250-SIZE 52428800\r\n250-STARTTLS\r\n250 AUTH LOGIN PLAIN\r\n");
    rewind($mem);
    $socket->injectStream($mem);

    $result = $socket->read();

    expect($result)->toContain('250-smtp.example.com')
        ->and($result)->toContain('SIZE 52428800')
        ->and($result)->toContain('STARTTLS')
        ->and($result)->toContain('AUTH LOGIN PLAIN');
});

it('writes raw bytes to the stream verbatim', function (): void {
    $socket = new TestableStreamSocket();
    $mem = fopen('php://memory', 'r+');
    $socket->injectStream($mem);

    $socket->write("EHLO client.example.com\r\n");

    rewind($mem);
    $written = stream_get_contents($mem);

    expect($written)->toBe("EHLO client.example.com\r\n");
});

it('throws a loud TransportException with the OS reason when the connection cannot be established', function (): void {
    // Bind an ephemeral port and close it again, so nothing is listening there
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    [, $port] = explode(':', stream_socket_get_name($server, false));
    fclose($server);

    $socket = new StreamSocket();

    $warnings = [];
    set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $socket->connect('127.0.0.1', (int) $port);
        $this->fail('Expected TransportException');
    } catch (TransportException $e) {
        expect($e->getContext())->toBe("Could not establish connection to 127.0.0.1:$port (Connection refused)");
    } finally {
        restore_error_handler();
    }

    expect($warnings)->toBeEmpty();
});

it('throws tlsFailed with the handshake reason and raises no PHP warning when STARTTLS fails', function (): void {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    expect($server)->not->toBeFalse();

    [, $port] = explode(':', stream_socket_get_name($server, false));

    $socket = new StreamSocket();
    $socket->connect('127.0.0.1', (int) $port);

    // The "server" answers the TLS ClientHello with plain text, so the handshake fails
    $connection = stream_socket_accept($server, 2);
    fwrite($connection, str_repeat("220 this is not TLS\r\n", 20));
    fclose($connection);

    $warnings = [];
    set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $socket->enableTls();
        $this->fail('Expected TransportException');
    } catch (TransportException $e) {
        expect($e->getMessage())->toBe('TLS negotiation failed.')
            ->and($e->getContext())->toStartWith('Could not establish secure connection to 127.0.0.1 (')
            ->and($e->getContext())->toContain('SSL operation failed');
    } finally {
        restore_error_handler();
        $socket->close();
        fclose($server);
    }

    expect($warnings)->toBeEmpty();
});

it('reports $connected as false before connect and after close, and true while the stream is open', function (): void {
    $socket = new StreamSocket();

    expect($socket->connected)->toBeFalse();

    // Use a loopback server/client pair to test $connected state transitions
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    expect($server)->not->toBeFalse();

    $serverName = stream_socket_get_name($server, false);
    [, $port] = explode(':', $serverName);

    $socket->connect('127.0.0.1', (int) $port);

    expect($socket->connected)->toBeTrue();

    $socket->close();

    expect($socket->connected)->toBeFalse();

    fclose($server);
});

it('opens a plain connection for tls so that STARTTLS can upgrade it', function (): void {
    // A plain TCP listener never answers a TLS handshake, so an implicit TLS socket would hang until the timeout
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    [, $port] = explode(':', stream_socket_get_name($server, false));

    $socket = new StreamSocket();
    $started = microtime(true);
    $socket->connect('127.0.0.1', (int) $port, 'tls', 2);

    expect($socket->connected)->toBeTrue()
        ->and(microtime(true) - $started)->toBeLessThan(1.0);

    $socket->close();
    fclose($server);
});
