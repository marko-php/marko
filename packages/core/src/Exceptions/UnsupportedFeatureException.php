<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Throwable;

class UnsupportedFeatureException extends MarkoException implements UnsupportedFeatureExceptionInterface
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

    public static function forDriver(
        string $feature,
        string $driver,
        string $suggestion,
        ?string $context = null,
        ?Throwable $previous = null,
    ): self {
        return self::fromUnsupportedFeature(
            new UnsupportedFeature(
                feature: $feature,
                driver: $driver,
                suggestion: $suggestion,
                context: $context,
            ),
            $previous,
        );
    }

    public static function fromUnsupportedFeature(
        UnsupportedFeature $unsupportedFeature,
        ?Throwable $previous = null,
    ): self {
        return new self($unsupportedFeature, $previous);
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
