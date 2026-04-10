<?php

declare(strict_types=1);

namespace Marko\Layout;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleRepositoryInterface;
use Error;
use Marko\Layout\Attributes\Component;
use Marko\Layout\Exceptions\DuplicateComponentException;
use ReflectionClass;
use ReflectionException;

readonly class DiscoveringComponentCollector implements ComponentCollectorInterface
{
    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
        private ClassFileParser $classFileParser,
        private ComponentCollector $componentCollector,
    ) {}

    /**
     * Collect components by discovering #[Component] classes from all module src/ directories,
     * merging with any explicitly passed $classNames, then delegating to inner collector.
     *
     * @param array<int, class-string> $classNames
     * @throws DuplicateComponentException|Error|ReflectionException
     */
    public function collect(array $classNames, string $handle): ComponentCollection
    {
        $discovered = $this->discoverComponentClasses();
        $merged = array_values(array_unique(array_merge($discovered, $classNames)));

        return $this->componentCollector->collect($merged, $handle);
    }

    /**
     * Discover a ComponentDefinition from a single class.
     *
     * @param class-string|string $className
     * @throws Error|ReflectionException
     */
    public function discoverFromClass(string $className): ?ComponentDefinition
    {
        return $this->componentCollector->discoverFromClass($className);
    }

    /**
     * Scan all modules for classes with the #[Component] attribute.
     *
     * @return array<int, string>
     * @throws ReflectionException
     */
    private function discoverComponentClasses(): array
    {
        $classes = [];

        foreach ($this->moduleRepository->all() as $module) {
            $srcPath = $module->path . '/src';

            if (!is_dir($srcPath)) {
                continue;
            }

            foreach ($this->classFileParser->findPhpFiles($srcPath) as $file) {
                $filePath = $file->getPathname();
                $className = $this->classFileParser->extractClassName($filePath);

                if ($className === null) {
                    continue;
                }

                if (!$this->classFileParser->loadClass($filePath, $className)) {
                    continue;
                }

                if ((new ReflectionClass($className))->getAttributes(Component::class) !== []) {
                    $classes[] = $className;
                }
            }
        }

        return $classes;
    }
}
