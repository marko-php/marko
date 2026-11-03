<?php

declare(strict_types=1);

namespace Marko\Http\Exceptions;

use Marko\Core\Exceptions\UnsupportedFeature;
use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Throwable;

class UnsupportedHttpFeatureException extends InvalidRequestOptionException implements UnsupportedFeatureExceptionInterface
{
    public function __construct(
        private readonly UnsupportedFeature $unsupportedFeature,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $unsupportedFeature->message(),
            context: $unsupportedFeature->context(),
            suggestion: $unsupportedFeature->suggestion,
            previous: $previous,
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
