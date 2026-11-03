<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\UnsupportedFeature;

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;

class CompliantBaseException extends MarkoException
{
    public static function featureUnsupported(string $driver): UnsupportedCompliantFeatureException
    {
        return new UnsupportedCompliantFeatureException(
            new UnsupportedFeature(feature: 'this', driver: $driver, suggestion: 'Fixture'),
        );
    }
}
