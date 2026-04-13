<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Closure;
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
     * @var array<string, array{module: string, source: string, require: array<string, string>}>
     */
    private array $bindings = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @throws BindingConflictException When same-priority modules bind the same interface
     */
    public function registerModule(
        ModuleManifest $module,
    ): void {
        $sourcePriority = self::SOURCE_PRIORITY[$module->source] ?? 0;

        foreach ($module->bindings as $interface => $implementation) {
            $this->registerBinding($interface, $implementation, $module, $sourcePriority);
        }

        foreach ($module->singletons as $interface => $implementation) {
            if (is_int($interface)) {
                // List-style: just mark as singleton, binding comes from 'bindings' or autowiring
                $this->container->singleton($implementation);
                continue;
            }
            $this->registerBinding($interface, $implementation, $module, $sourcePriority);
            $this->container->singleton($interface);
        }
    }

    /**
     * @throws BindingConflictException
     */
    private function registerBinding(
        string $interface,
        string|Closure $implementation,
        ModuleManifest $module,
        int $sourcePriority,
    ): void {
        if (isset($this->bindings[$interface])) {
            $existingBinding = $this->bindings[$interface];
            $existingPriority = self::SOURCE_PRIORITY[$existingBinding['source']] ?? 0;

            // Same-priority bindings normally conflict, but a later module may
            // intentionally override a dependency it builds on top of.
            if ($sourcePriority === $existingPriority) {
                if (! $this->canOverrideSamePriorityBinding($existingBinding['module'], $module)) {
                    throw BindingConflictException::multipleBindings(
                        $interface,
                        [$existingBinding['module'], $module->name],
                    );
                }
            }

            // Lower priority cannot override higher priority
            if ($sourcePriority < $existingPriority) {
                return;
            }
        }

        $this->bindings[$interface] = [
            'module' => $module->name,
            'source' => $module->source,
            'require' => $module->require,
        ];

        $this->container->bind($interface, $implementation);
    }

    private function canOverrideSamePriorityBinding(
        string $existingModule,
        ModuleManifest $module,
    ): bool {
        return array_key_exists($existingModule, $module->require);
    }
}
