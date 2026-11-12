<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T004Variadic
{
    /** @var array<int, string> */
    public array $items;

    public function __construct(
        string ...$items,
    ) {
        $this->items = $items;
    }
}
