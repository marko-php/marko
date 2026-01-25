<?php

declare(strict_types=1);

namespace Marko\Filesystem\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class FilesystemDriver
{
    public function __construct(
        public string $name,
    ) {}
}
