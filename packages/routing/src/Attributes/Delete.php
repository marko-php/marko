<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Delete extends Route
{
    public function getMethod(): string
    {
        return 'DELETE';
    }
}
