<?php

declare(strict_types=1);

namespace Marko\Authorization\Discovery;

use Marko\Authorization\Contracts\GateHookInterface;
use Marko\Authorization\Exceptions\GateHookException;
use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;

/**
 * Stores the discovered gate hooks in the discovery cache, in run order, so a
 * cached boot builds the hook list without scanning the modules.
 */
readonly class GateHookCacheContributor implements DiscoveryCacheContributorInterface
{
    public const string KEY = 'gate_hooks';

    public function __construct(
        private GateHookDiscovery $gateHookDiscovery,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return array<int, array{className: string, priority: int}>
     * @throws GateHookException
     */
    public function compile(
        array $modules,
    ): array {
        return array_map(
            fn (GateHookDefinition $definition): array => [
                'className' => $definition->className,
                'priority' => $definition->priority,
            ],
            $this->gateHookDiscovery->discoverAll($modules),
        );
    }

    /**
     * Rebuild the hook definitions from a cached section, keeping its order.
     *
     * @param array<mixed> $section
     * @return array<int, GateHookDefinition>
     * @throws DiscoveryCacheException
     */
    public function hydrate(
        array $section,
    ): array {
        $definitions = [];

        foreach ($section as $index => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformedSection(self::KEY, "gate hook $index must be an array");
            }

            $className = $record['className'] ?? null;
            $priority = $record['priority'] ?? null;

            if (!is_string($className)) {
                throw DiscoveryCacheException::malformedSection(
                    self::KEY,
                    "gate hook $index.className must be a string",
                );
            }

            if (!is_int($priority)) {
                throw DiscoveryCacheException::malformedSection(
                    self::KEY,
                    "gate hook $index.priority must be an int",
                );
            }

            /** @var class-string<GateHookInterface> $className */
            $definitions[] = new GateHookDefinition(
                className: $className,
                priority: $priority,
            );
        }

        return $definitions;
    }
}
