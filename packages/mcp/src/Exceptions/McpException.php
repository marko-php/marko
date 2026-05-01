<?php

declare(strict_types=1);

namespace Marko\Mcp\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class McpException extends MarkoException
{
    private const int PARSE_ERROR = -32700;

    private const int INVALID_REQUEST = -32600;

    private const int METHOD_NOT_FOUND = -32601;

    private const int INTERNAL_ERROR = -32603;

    private int $jsonRpcCode;

    public function __construct(
        string $message,
        int $jsonRpcCode,
        string $context = '',
        string $suggestion = '',
    ) {
        parent::__construct(
            message: $message,
            context: $context,
            suggestion: $suggestion,
            code: $jsonRpcCode,
        );
        $this->jsonRpcCode = $jsonRpcCode;
    }

    public function getJsonRpcCode(): int
    {
        return $this->jsonRpcCode;
    }

    public static function methodNotFound(string $method): self
    {
        return new self(
            message: "Method not found: $method",
            jsonRpcCode: self::METHOD_NOT_FOUND,
        );
    }

    public static function invalidRequest(string $reason): self
    {
        return new self(
            message: "Invalid Request: $reason",
            jsonRpcCode: self::INVALID_REQUEST,
        );
    }

    public static function parseError(string $reason): self
    {
        return new self(
            message: "Parse error: $reason",
            jsonRpcCode: self::PARSE_ERROR,
        );
    }

    public static function internalError(string $message): self
    {
        return new self(
            message: "Internal error: $message",
            jsonRpcCode: self::INTERNAL_ERROR,
        );
    }
}
