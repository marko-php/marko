<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;
use Marko\Database\Entity\Entity;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class ExtensionOf
{
    /**
     * @param class-string<Entity> $entityClass
     */
    public function __construct(
        public string $entityClass,
    ) {}
}
