<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\UnsupportedFeature;

use Marko\Core\Exceptions\MarkoException;

class ViolatingByNameException extends MarkoException
{
    public static function featureUnsupported(string $driver): self
    {
        return new self(
            message: "Driver '$driver' lacks this",
            context: 'Fixture',
            suggestion: 'Fixture',
        );
    }
}
