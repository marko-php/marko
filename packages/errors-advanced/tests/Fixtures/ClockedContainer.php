<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Tests\Fixtures;

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

/**
 * A bare container with the ClockInterface that marko/clock's module would bind.
 */
class ClockedContainer
{
    public static function create(): Container
    {
        $container = new Container(new PreferenceRegistry());
        $container->instance(ClockInterface::class, new FakeClock());

        return $container;
    }

    /**
     * The same container with a module.php array's bindings and arguments registered.
     *
     * @param array<string, mixed> $module
     */
    public static function withModule(array $module): Container
    {
        $container = self::create();

        foreach ($module['bindings'] as $abstract => $concrete) {
            $container->bind($abstract, $concrete);
        }

        foreach ($module['arguments'] ?? [] as $class => $arguments) {
            $container->setArguments($class, $arguments);
        }

        return $container;
    }
}
