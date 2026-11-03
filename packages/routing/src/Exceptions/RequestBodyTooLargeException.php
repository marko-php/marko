<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Core\Exceptions\MarkoException;

/**
 * The request body is larger than the configured limit (`post_max_size` for a POST), so it
 * was not read. Rendered as 413 Content Too Large by the routing pipeline wherever it is thrown.
 */
class RequestBodyTooLargeException extends MarkoException implements HttpExceptionInterface
{
    public static function fromLengths(
        int $declaredLength,
        int $maxLength,
    ): self {
        return new self(
            message: 'The request body is too large.',
            context: "The request declared a $declaredLength-byte body, over the $maxLength-byte limit (post_max_size). PHP discarded the parsed form data and uploaded files, and the body was not read.",
            suggestion: 'Send a smaller body, or raise post_max_size (and upload_max_filesize for uploads) in php.ini.',
        );
    }

    public function getStatusCode(): int
    {
        return 413;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return ['message' => $this->getMessage()];
    }
}
