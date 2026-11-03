<?php

declare(strict_types=1);

namespace Marko\Mcp\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;

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

    public static function readOnlyUnsupportedDriver(string $driver): UnsupportedMcpFeatureException
    {
        return new UnsupportedMcpFeatureException(
            unsupportedFeature: new UnsupportedFeature(
                feature: 'read-only queries',
                driver: $driver,
                suggestion: 'Use a mysql, pgsql or sqlite connection for the MCP server, or add read-only transaction support for this driver to MarkoQueryConnection.',
                context: 'query_database runs every read inside a read-only transaction so a crafted query cannot write. '
                    . "It only knows how to open one on mysql, pgsql and sqlite connections, so it refuses to run the query on '$driver' at all.",
                message: "query_database cannot run a read-only query on database driver '$driver'",
            ),
            jsonRpcCode: self::INTERNAL_ERROR,
        );
    }

    public static function productionServeRefused(string $environment): self
    {
        return new self(
            message: "Refusing to start the MCP server in the '$environment' environment",
            jsonRpcCode: self::INTERNAL_ERROR,
            context: 'The MCP server lets an AI agent run console commands, read logs and query the database. '
                . 'Content the agent reads can steer it, so the server does not start in production by default. '
                . 'An unset APP_ENV/MARKO_ENV counts as production.',
            suggestion: 'Set APP_ENV=local (or development) on development machines. To serve a production '
                . 'environment on purpose, set MCP_ALLOW_PRODUCTION=true (config key mcp.allow_production).',
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
