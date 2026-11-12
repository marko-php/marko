<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T004EnumSubject
{
    public function __construct(
        public T004Suit $suit,
    ) {}
}
