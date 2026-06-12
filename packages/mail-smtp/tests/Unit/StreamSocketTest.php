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
    public function injectStream(mixed $stream): void
    {
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

it('throws a loud TransportException when the connection cannot be established', function (): void {
    $socket = new StreamSocket();

    // Connect to a port that is not listening (likely no service on port 1)
    expect(fn () => $socket->connect('127.0.0.1', 1))
        ->toThrow(TransportException::class);
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
