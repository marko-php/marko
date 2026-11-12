<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Closure;
use Marko\Core\Exceptions\ArgumentConflictException;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Module\ModuleManifest;

class BindingRegistry
{
    public const array SOURCE_PRIORITY = [
        'vendor' => 0,
        'modules' => 1,
        'app' => 2,
    ];

    /**
     * @var array<string, array{module: string, source: string}>
     */
    private array $bindings = [];

    private readonly ArgumentRegistry $arguments;

    public function __construct(
        private readonly Container $container,
    ) {
        $this->arguments = new ArgumentRegistry($container);
    }

    /**
     * @throws BindingConflictException|ArgumentConflictException When same-priority modules bind the same interface or set the same argument
     */
    public function registerModule(
        ModuleManifest $module,
    ): void {
        $sourcePriority = self::SOURCE_PRIORITY[$module->source] ?? 0;

        $this->arguments->registerModule($module);

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

            // Same priority = conflict
            if ($sourcePriority === $existingPriority) {
                throw BindingConflictException::multipleBindings(
                    $interface,
                    [$existingBinding['module'], $module->name],
                );
            }

            // Lower priority cannot override higher priority
            if ($sourcePriority < $existingPriority) {
                return;
            }
        }

        $this->bindings[$interface] = [
            'module' => $module->name,
            'source' => $module->source,
        ];

        $this->container->bind($interface, $implementation);

        if ($implementation instanceof Closure) {
            $this->arguments->dropOutranked($interface, $sourcePriority);
        }
    }
}
