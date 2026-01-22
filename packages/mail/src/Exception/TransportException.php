<?php

declare(strict_types=1);

namespace Marko\Mail\Exception;

class TransportException extends MailException
{
    public static function connectionFailed(
        string $host,
        int $port,
    ): self {
        return new self(
            message: 'Failed to connect to mail server.',
            context: "Could not establish connection to $host:$port",
            suggestion: 'Verify the server address and port are correct and the server is reachable.',
        );
    }

    public static function tlsFailed(
        string $host,
    ): self {
        return new self(
            message: 'TLS negotiation failed.',
            context: "Could not establish secure connection to $host",
            suggestion: 'Check server TLS configuration or try disabling TLS verification for testing.',
        );
    }

    public static function authenticationFailed(
        string $username,
    ): self {
        return new self(
            message: 'SMTP authentication failed.',
            context: "Failed to authenticate as $username",
            suggestion: 'Verify your username and password are correct.',
        );
    }

    public static function unexpectedResponse(
        int $code,
        string $response,
    ): self {
        return new self(
            message: 'Unexpected SMTP response.',
            context: "Server responded with code $code: $response",
            suggestion: 'Check the recipient address and server error message for details.',
        );
    }
}
