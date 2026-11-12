<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Exceptions\ArgumentConflictException;
use Marko\Core\Module\ModuleManifest;

class ArgumentRegistry
{
    /**
     * @var array<class-string, array<string, array{value: mixed, priority: int, module: string}>>
     */
    private array $merged = [];

    /**
     * @var array<class-string, int>
     */
    private array $closurePriorities = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @throws ArgumentConflictException When same-priority modules set the same argument of the same class
     */
    public function registerModule(
        ModuleManifest $module,
    ): void {
        $priority = BindingRegistry::SOURCE_PRIORITY[$module->source] ?? 0;

        foreach ($module->arguments as $class => $arguments) {
            foreach ($arguments as $parameter => $value) {
                if ($priority < ($this->closurePriorities[$class] ?? -1)) {
                    continue;
                }

                $existing = $this->merged[$class][$parameter] ?? null;

                if ($existing !== null && $priority === $existing['priority']) {
                    throw ArgumentConflictException::multipleModules(
                        $class,
                        $parameter,
                        [$existing['module'], $module->name],
                    );
                }

                if ($existing === null || $priority > $existing['priority']) {
                    $this->merged[$class][$parameter] = [
                        'value' => $value,
                        'priority' => $priority,
                        'module' => $module->name,
                    ];
                }
            }

            $this->applyToContainer($class);
        }
    }

    /**
     * Drop the arguments of a class that a strictly higher-priority module closure-binds.
     */
    public function dropOutranked(
        string $class,
        int $priority,
    ): void {
        $this->closurePriorities[$class] = $priority;

        foreach ($this->merged[$class] ?? [] as $parameter => $entry) {
            if ($entry['priority'] < $priority) {
                unset($this->merged[$class][$parameter]);
            }
        }

        $this->applyToContainer($class);
    }

    private function applyToContainer(
        string $class,
    ): void {
        $this->container->setArguments(
            $class,
            array_map(fn (array $entry): mixed => $entry['value'], $this->merged[$class] ?? []),
        );
    }
}
