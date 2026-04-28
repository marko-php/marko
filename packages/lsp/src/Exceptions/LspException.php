<?php

declare(strict_types=1);

namespace Marko\Lsp\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class LspException extends MarkoException
{
    public function getJsonRpcCode(): int
    {
        return $this->getCode() !== 0 ? $this->getCode() : -32603;
    }

    public static function methodNotFound(string $method): self
    {
        return new self(
            message: "Method not found: $method",
            code: -32601,
        );
    }
}
