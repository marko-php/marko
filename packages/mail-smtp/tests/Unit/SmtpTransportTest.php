<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Unit;

use Marko\Mail\Exception\TransportException;
use Marko\Mail\Smtp\SmtpTransport;
use Marko\Mail\Smtp\SocketInterface;

test('SmtpTransport connects to server', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    expect($socket->connected)->toBeTrue();
});

test('SmtpTransport throws on connection failure', function (): void {
    $socket = createFailingSocket();

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
})->throws(TransportException::class);

test('SmtpTransport sends EHLO command', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        "250-smtp.example.com\r\n250-SIZE 52428800\r\n250-STARTTLS\r\n250 AUTH LOGIN PLAIN",
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $capabilities = $transport->ehlo('client.example.com');

    expect($socket->written)->toContain("EHLO client.example.com\r\n")
        ->and($capabilities)->toContain('SIZE 52428800')
        ->and($capabilities)->toContain('STARTTLS')
        ->and($capabilities)->toContain('AUTH LOGIN PLAIN');
});

test('SmtpTransport handles STARTTLS', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '220 Ready to start TLS',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->startTls();

    expect($socket->written)->toContain("STARTTLS\r\n")
        ->and($socket->tlsEnabled)->toBeTrue();
});

test('SmtpTransport throws on TLS failure', function (): void {
    $socket = createMockSocket(
        responses: [
            '220 smtp.example.com ESMTP ready',
            '220 Ready to start TLS',
        ],
        tlsSuccess: false,
    );

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->startTls();
})->throws(TransportException::class);

test('SmtpTransport authenticates with LOGIN', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '334 VXNlcm5hbWU6', // Base64 "Username:"
        '334 UGFzc3dvcmQ6', // Base64 "Password:"
        '235 Authentication successful',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->authenticate('user@example.com', 'secret');

    $written = $socket->written;
    expect($written)->toContain("AUTH LOGIN\r\n")
        ->and($written)->toContain(base64_encode('user@example.com') . "\r\n")
        ->and($written)->toContain(base64_encode('secret') . "\r\n");
});

test('SmtpTransport authenticates with PLAIN', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '235 Authentication successful',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->authenticate('user@example.com', 'secret', 'PLAIN');

    $written = $socket->written;
    $expectedCredentials = base64_encode("\0user@example.com\0secret");
    expect($written)->toContain("AUTH PLAIN $expectedCredentials\r\n");
});

test('SmtpTransport throws on auth failure', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '535 Authentication failed',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->authenticate('user@example.com', 'wrongpassword', 'PLAIN');
})->throws(TransportException::class);

test('SmtpTransport sends MAIL FROM command', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->mailFrom('sender@example.com');

    expect($socket->written)->toContain("MAIL FROM:<sender@example.com>\r\n");
});

test('SmtpTransport sends RCPT TO command', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->rcptTo('recipient@example.com');

    expect($socket->written)->toContain("RCPT TO:<recipient@example.com>\r\n");
});

test('SmtpTransport sends DATA command', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '354 Start mail input',
        '250 OK',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->data("Subject: Test\r\n\r\nHello World");

    $written = $socket->written;
    expect($written)->toContain("DATA\r\n")
        ->and($written)->toContain("Subject: Test\r\n\r\nHello World\r\n.\r\n");
});

test('SmtpTransport handles unexpected response codes', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        '550 User not found',
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->rcptTo('invalid@example.com');
})->throws(TransportException::class);

test('SmtpTransport handles multi-line responses', function (): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        "250-mail.example.com Hello\r\n250-SIZE 52428800\r\n250-8BITMIME\r\n250 HELP",
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $capabilities = $transport->ehlo('client.example.com');

    expect($capabilities)->toContain('mail.example.com Hello')
        ->and($capabilities)->toContain('SIZE 52428800')
        ->and($capabilities)->toContain('8BITMIME')
        ->and($capabilities)->toContain('HELP');
});

test('SmtpTransport handles connection timeout', function (): void {
    $socket = createTimeoutSocket();

    $transport = new SmtpTransport($socket);
    $transport->connect('slow.example.com', 587);
})->throws(TransportException::class, 'Failed to connect to mail server.');

test('SmtpTransport handles server disconnect', function (): void {
    $socket = createDisconnectingSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK',
        '', // Server disconnects, returns empty response
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->mailFrom('sender@example.com');

    // Server disconnects before RCPT TO can complete
    $transport->rcptTo('recipient@example.com');
})->throws(TransportException::class, 'Unexpected SMTP response.');

test('SmtpTransport handles various SMTP error codes', function (
    int $errorCode,
    string $errorMessage,
): void {
    $socket = createMockSocket([
        '220 smtp.example.com ESMTP ready',
        "$errorCode $errorMessage",
    ]);

    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);
    $transport->mailFrom('sender@example.com');
})->throws(TransportException::class, 'Unexpected SMTP response.')
    ->with([
        '421 - Service not available' => [421, 'Service not available, closing transmission channel'],
        '450 - Mailbox unavailable' => [450, 'Requested mail action not taken: mailbox unavailable'],
        '500 - Syntax error' => [500, 'Syntax error, command unrecognized'],
        '550 - Requested action not taken' => [550, 'Requested action not taken: mailbox unavailable'],
    ]);

function createFailingSocket(): SocketInterface
{
    return new class () implements SocketInterface
    {
        public bool $connected = false;

        public function connect(
            string $host,
            int $port,
            ?string $encryption = null,
            int $timeout = 30,
        ): void {
            throw TransportException::connectionFailed($host, $port);
        }

        public function read(): string
        {
            return '';
        }

        public function write(string $data): void {}

        public function enableTls(): bool
        {
            return false;
        }

        public function close(): void {}
    };
}

function createTimeoutSocket(): SocketInterface
{
    return new class () implements SocketInterface
    {
        public bool $connected = false;

        public function connect(
            string $host,
            int $port,
            ?string $encryption = null,
            int $timeout = 30,
        ): void {
            // Simulate timeout by throwing connection failed exception
            throw TransportException::connectionFailed($host, $port);
        }

        public function read(): string
        {
            return '';
        }

        public function write(string $data): void {}

        public function enableTls(): bool
        {
            return false;
        }

        public function close(): void {}
    };
}

function createDisconnectingSocket(
    array $responses,
): SocketInterface {
    return new class ($responses) implements SocketInterface
    {
        public private(set) bool $connected = false;

        private int $responseIndex = 0;

        public function __construct(
            private readonly array $responses,
        ) {}

        public function connect(
            string $host,
            int $port,
            ?string $encryption = null,
            int $timeout = 30,
        ): void {
            $this->connected = true;
        }

        public function read(): string
        {
            $response = $this->responses[$this->responseIndex++] ?? '';

            // Simulate disconnect when empty response
            if ($response === '') {
                $this->connected = false;
            }

            return $response;
        }

        public function write(string $data): void {}

        public function enableTls(): bool
        {
            return true;
        }

        public function close(): void
        {
            $this->connected = false;
        }
    };
}

function createMockSocket(
    array $responses,
    bool $tlsSuccess = true,
): MockSocket {
    return new MockSocket($responses, $tlsSuccess);
}

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
