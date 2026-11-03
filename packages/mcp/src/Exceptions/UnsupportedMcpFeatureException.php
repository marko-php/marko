<?php

declare(strict_types=1);

namespace Marko\Mcp\Exceptions;

use Marko\Core\Exceptions\UnsupportedFeature;
use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;

class UnsupportedMcpFeatureException extends McpException implements UnsupportedFeatureExceptionInterface
{
    public function __construct(
        private readonly UnsupportedFeature $unsupportedFeature,
        int $jsonRpcCode,
    ) {
        parent::__construct(
            message: $unsupportedFeature->message(),
            jsonRpcCode: $jsonRpcCode,
            context: $unsupportedFeature->context(),
            suggestion: $unsupportedFeature->suggestion,
        );
    }

    public function getFeature(): string
    {
        return $this->unsupportedFeature->feature;
    }

    public function getDriver(): string
    {
        return $this->unsupportedFeature->driver;
    }
}
