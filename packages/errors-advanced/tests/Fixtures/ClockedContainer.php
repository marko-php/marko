<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Tests\Fixtures;

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
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

        new BindingRegistry($container)->registerModule(new ModuleManifest(
            name: 'marko/errors-advanced',
            version: '1.0.0',
            bindings: $module['bindings'],
            arguments: $module['arguments'] ?? [],
            source: 'vendor',
        ));

        return $container;
    }
}
