<?php

declare(strict_types=1);

namespace Marko\Layout\Tests\Unit;

use Closure;
use Marko\Core\Container\ContainerInterface;

final class Helpers
{
    public static function stubContainer(): ContainerInterface
    {
        return new class () implements ContainerInterface
        {
            public function get(string $id): object
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }

            public function singleton(string $id): void {}

            public function instance(string $id, object $instance): void {}

            public function call(Closure $callable): mixed
            {
                return $callable();
            }
        };
    }
}
