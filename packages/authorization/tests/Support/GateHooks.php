<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Support;

use Marko\Authorization\Contracts\GateHookInterface;
use Marko\Authorization\Discovery\GateHookCacheContributor;
use Marko\Authorization\Discovery\GateHookDiscovery;
use Marko\Authorization\GateHookRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleRepository;

/**
 * Builds a real GateHookRegistry for tests, as if the hooks came from the discovery cache.
 */
class GateHooks
{
    /**
     * The hooks run in the order given: earlier hooks get a higher priority.
     */
    public static function registry(
        GateHookInterface ...$hooks,
    ): GateHookRegistry {
        $container = new Container();
        $records = [];
        $priority = count($hooks);

        foreach ($hooks as $hook) {
            $container->instance($hook::class, $hook);
            $records[] = ['className' => $hook::class, 'priority' => $priority--];
        }

        $discovery = new GateHookDiscovery();

        return new GateHookRegistry(
            cachedDiscovery: new CachedDiscovery([GateHookCacheContributor::KEY => $records]),
            gateHookCacheContributor: new GateHookCacheContributor($discovery),
            gateHookDiscovery: $discovery,
            moduleRepository: new ModuleRepository([]),
            container: $container,
        );
    }
}
