<?php

declare(strict_types=1);

namespace Marko\Authorization;

use Marko\Authorization\Contracts\GateHookInterface;
use Marko\Authorization\Discovery\GateHookCacheContributor;
use Marko\Authorization\Discovery\GateHookDiscovery;
use Marko\Authorization\Exceptions\GateHookException;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleRepositoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * The #[GateHook] classes the Gate runs before closures and policies.
 *
 * A boot from the discovery cache reads the 'gate_hooks' section; any other
 * boot scans the enabled modules. Either way it happens on the first call to
 * hooks(), and each hook is built once through the container, so hooks can
 * inject their dependencies.
 */
class GateHookRegistry
{
    /** @var array<int, GateHookInterface>|null */
    private ?array $hooks = null;

    public function __construct(
        private readonly CachedDiscovery $cachedDiscovery,
        private readonly GateHookCacheContributor $gateHookCacheContributor,
        private readonly GateHookDiscovery $gateHookDiscovery,
        private readonly ModuleRepositoryInterface $moduleRepository,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * The hooks in run order, highest priority first.
     *
     * @return array<int, GateHookInterface>
     * @throws DiscoveryCacheException|GateHookException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function hooks(): array
    {
        if ($this->hooks === null) {
            $section = $this->cachedDiscovery->section(GateHookCacheContributor::KEY);
            $definitions = $section !== null
                ? $this->gateHookCacheContributor->hydrate($section)
                : $this->gateHookDiscovery->discoverAll($this->moduleRepository->all());

            $hooks = [];

            foreach ($definitions as $definition) {
                /** @var GateHookInterface $hook */
                $hook = $this->container->get($definition->className);
                $hooks[] = $hook;
            }

            $this->hooks = $hooks;
        }

        return $this->hooks;
    }
}
