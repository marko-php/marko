<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Mail\Exception\TransportException;

class SmtpTransport
{
    private string $host = '';

    private string $username = '';

    public function __construct(
        private SocketInterface $socket,
    ) {}

    public function connect(
        string $host,
        int $port,
        ?string $encryption = null,
    ): void {
        $this->host = $host;
        $this->socket->connect($host, $port, $encryption);
        $this->socket->read();
    }

    /**
     * @return array<string> List of server capabilities
     */
    public function ehlo(
        string $hostname,
    ): array {
        $this->socket->write("EHLO $hostname\r\n");
        $response = $this->socket->read();

        return $this->parseCapabilities($response);
    }

    public function startTls(): void
    {
        $this->socket->write("STARTTLS\r\n");
        $this->socket->read();

        if (!$this->socket->enableTls()) {
            throw TransportException::tlsFailed($this->host);
        }
    }

    public function authenticate(
        string $username,
        string $password,
        string $mode = 'LOGIN',
    ): void {
        $this->username = $username;

        if ($mode === 'LOGIN') {
            $this->authenticateLogin($username, $password);
        } elseif ($mode === 'PLAIN') {
            $this->authenticatePlain($username, $password);
        }
    }

    private function authenticateLogin(
        string $username,
        string $password,
    ): void {
        $this->socket->write("AUTH LOGIN\r\n");
        $response = $this->socket->read();
        $this->expectResponseCode($response, 334);

        $this->socket->write(base64_encode($username) . "\r\n");
        $response = $this->socket->read();
        $this->expectResponseCode($response, 334);

        $this->socket->write(base64_encode($password) . "\r\n");
        $response = $this->socket->read();
        $this->expectAuthSuccess($response);
    }

    private function authenticatePlain(
        string $username,
        string $password,
    ): void {
        $credentials = base64_encode("\0$username\0$password");
        $this->socket->write("AUTH PLAIN $credentials\r\n");
        $response = $this->socket->read();
        $this->expectAuthSuccess($response);
    }

    public function mailFrom(
        string $address,
    ): void {
        $this->socket->write("MAIL FROM:<$address>\r\n");
        $response = $this->socket->read();
        $this->expectSuccess($response);
    }

    public function rcptTo(
        string $address,
    ): void {
        $this->socket->write("RCPT TO:<$address>\r\n");
        $response = $this->socket->read();
        $this->expectSuccess($response);
    }

    public function data(
        string $content,
    ): void {
        $this->socket->write("DATA\r\n");
        $response = $this->socket->read();
        $this->expectResponseCode($response, 354);

        $this->socket->write($content . "\r\n.\r\n");
        $response = $this->socket->read();
        $this->expectSuccess($response);
    }

    public function quit(): void
    {
        $this->socket->write("QUIT\r\n");
        $this->socket->read();
        $this->socket->close();
    }

    private function expectAuthSuccess(
        string $response,
    ): void {
        $code = $this->parseResponseCode($response);

        if ($code !== 235) {
            throw TransportException::authenticationFailed($this->username);
        }
    }

    private function expectSuccess(
        string $response,
    ): void {
        $code = $this->parseResponseCode($response);

        if ($code < 200 || $code >= 300) {
            throw TransportException::unexpectedResponse($code, $response);
        }
    }

    private function expectResponseCode(
        string $response,
        int $expected,
    ): void {
        $code = $this->parseResponseCode($response);

        if ($code !== $expected) {
            throw TransportException::unexpectedResponse($code, $response);
        }
    }

    private function parseResponseCode(
        string $response,
    ): int {
        if (preg_match('/^(\d{3})/', $response, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return array<string>
     */
    private function parseCapabilities(
        string $response,
    ): array {
        $capabilities = [];
        $lines = explode("\r\n", $response);

        foreach ($lines as $line) {
            if (preg_match('/^250[- ](.+)$/', $line, $matches)) {
                $capabilities[] = $matches[1];
            }
        }

        return $capabilities;
    }
}
