<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

/**
 * A signed URL failed validation. Renders as 403; the message says whether
 * the link was invalid (missing or tampered signature) or has expired.
 */
class InvalidSignatureException extends HttpException
{
    public function __construct(
        string $message,
        private readonly bool $expired = false,
    ) {
        parent::__construct(statusCode: 403, message: $message);
    }

    public static function invalid(): self
    {
        return new self(message: 'This link is invalid.');
    }

    public static function expired(): self
    {
        return new self(message: 'This link has expired.', expired: true);
    }

    public function isExpired(): bool
    {
        return $this->expired;
    }
}
