<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\UnsupportedFeature;

use Marko\Core\Exceptions\MarkoException;

class ViolatingByMessageException extends MarkoException
{
    public static function noTransactions(string $driver): self
    {
        return new self(
            message: "The '$driver' driver does not support transactions",
            context: 'Fixture',
            suggestion: 'Fixture',
        );
    }
}
