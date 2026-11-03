<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\UnsupportedFeature;

use Marko\Core\Exceptions\MarkoException;

class AllowlistedException extends MarkoException
{
    public static function unsupportedOption(string $option): self
    {
        return new self(
            message: "Unsupported option '$option'",
            context: 'Fixture input validation',
            suggestion: 'Fixture',
        );
    }
}
