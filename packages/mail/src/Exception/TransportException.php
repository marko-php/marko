<?php

declare(strict_types=1);

namespace Marko\Mail\Exception;

class TransportException extends MailException
{
    public static function connectionFailed(
        string $host,
        int $port,
        ?string $reason = null,
    ): self {
        return new self(
            message: 'Failed to connect to mail server.',
            context: self::withReason("Could not establish connection to $host:$port", $reason),
            suggestion: 'Verify the server address and port are correct and the server is reachable.',
        );
    }

    public static function tlsFailed(
        string $host,
        ?string $reason = null,
    ): self {
        return new self(
            message: 'TLS negotiation failed.',
            context: self::withReason("Could not establish secure connection to $host", $reason),
            suggestion: 'Check server TLS configuration or try disabling TLS verification for testing.',
        );
    }

    private static function withReason(
        string $context,
        ?string $reason,
    ): string {
        return ($reason === null || $reason === '')
            ? $context
            : "$context ($reason)";
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

    public static function commandInjection(
        string $command,
        string $address,
    ): self {
        $safe = addcslashes($address, "\r\n\0");

        return new self(
            message: "SMTP command injection attempt detected in $command address.",
            context: "The $command address '$safe' contains CR, LF or NUL characters",
            suggestion: 'Pass a single validated email address, for example by constructing a Marko\\Mail\\Address first',
        );
    }
}
