<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

class T006Counted
{
    public static int $built = 0;

    public function __construct(
        public readonly string $host = 'localhost',
    ) {
        self::$built++;
    }
}
