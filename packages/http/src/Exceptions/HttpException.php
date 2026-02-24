<?php

declare(strict_types=1);

namespace Marko\Http\Exceptions;

use Exception;
use Marko\Http\HttpResponse;
use Throwable;

class HttpException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?HttpResponse $response = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): ?HttpResponse
    {
        return $this->response;
    }
}
