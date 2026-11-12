<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T006Needs
{
    public function __construct(
        public T006Contract $contract,
    ) {}
}
