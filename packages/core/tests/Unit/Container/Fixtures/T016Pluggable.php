<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

class T016Pluggable
{
    public function __construct(
        private readonly string $label = 'default',
    ) {}

    public function getLabel(): string
    {
        return $this->label;
    }
}
