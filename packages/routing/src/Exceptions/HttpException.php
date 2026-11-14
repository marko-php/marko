<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use InvalidArgumentException;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Core\Exceptions\MarkoException;
use Marko\Routing\Http\HttpStatus;
use Throwable;

/**
 * Throw from a controller or middleware to respond with an HTTP error status.
 *
 * The routing pipeline renders it as JSON or HTML at the depth it was thrown,
 * so outer middleware (CORS, security headers, session) still decorates the
 * response. The message is client-facing: it is sent as `message` in the body.
 */
class HttpException extends MarkoException implements HttpExceptionInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $data Extra client-safe data merged into the response body
     * @throws InvalidArgumentException When the status code is not 400-599
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
        private readonly array $data = [],
        string $context = '',
        string $suggestion = '',
        ?Throwable $previous = null,
    ) {
        if (
            $statusCode < 400
            || $statusCode > 599
        ) {
            throw new InvalidArgumentException(
                "HttpException status code must be between 400 and 599, got $statusCode. "
                . 'Return a Response (e.g. Response::redirect()) for non-error statuses.',
            );
        }

        parent::__construct(
            message: $message !== '' ? $message : HttpStatus::reasonPhrase($statusCode),
            context: $context,
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return ['message' => $this->getMessage(), ...$this->data];
    }

    public static function badRequest(
        string $message = '',
    ): self {
        return new self(statusCode: 400, message: $message);
    }

    public static function unauthorized(
        string $message = '',
    ): self {
        return new self(statusCode: 401, message: $message);
    }

    public static function forbidden(
        string $message = '',
    ): self {
        return new self(statusCode: 403, message: $message);
    }

    public static function notFound(
        string $message = '',
    ): self {
        return new self(statusCode: 404, message: $message);
    }

    /**
     * @param array<string> $allowedMethods Sent verbatim (uppercased, de-duplicated) as the `Allow` header
     */
    public static function methodNotAllowed(
        array $allowedMethods,
        string $message = '',
    ): self {
        $allow = implode(', ', array_values(array_unique(array_map(strtoupper(...), $allowedMethods))));

        return new self(statusCode: 405, message: $message, headers: ['Allow' => $allow]);
    }

    public static function conflict(
        string $message = '',
    ): self {
        return new self(statusCode: 409, message: $message);
    }

    public static function tooManyRequests(
        ?int $retryAfter = null,
        string $message = '',
    ): self {
        $headers = $retryAfter !== null ? ['Retry-After' => (string) $retryAfter] : [];

        return new self(statusCode: 429, message: $message, headers: $headers);
    }
}
