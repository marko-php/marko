<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Module\ModuleManifest;

class BindingRegistry
{
    private const array SOURCE_PRIORITY = [
        'vendor' => 0,
        'modules' => 1,
        'app' => 2,
    ];

    /**
     * @var array<string, array{module: string, source: string}>
     */
    private array $bindings = [];

    public function __construct(
        private Container $container,
    ) {}

    /**
     * @throws BindingConflictException When same-priority modules bind the same interface
     */
    public function registerModule(ModuleManifest $module): void
    {
        $sourcePriority = self::SOURCE_PRIORITY[$module->source] ?? 0;

        foreach ($module->bindings as $interface => $implementation) {
            if (isset($this->bindings[$interface])) {
                $existingBinding = $this->bindings[$interface];
                $existingPriority = self::SOURCE_PRIORITY[$existingBinding['source']] ?? 0;

                // Same priority = conflict
                if ($sourcePriority === $existingPriority) {
                    throw BindingConflictException::multipleBindings(
                        $interface,
                        [$existingBinding['module'], $module->name],
                    );
                }

                // Lower priority cannot override higher priority
                if ($sourcePriority < $existingPriority) {
                    continue;
                }
            }

            $this->bindings[$interface] = [
                'module' => $module->name,
                'source' => $module->source,
            ];

            $this->container->bind($interface, $implementation);
        }
    }
}
