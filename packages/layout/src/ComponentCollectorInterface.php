<?php

declare(strict_types=1);

namespace Marko\Layout;

interface ComponentCollectorInterface
{
    /**
     * Collect all components from the given class names that match the given handle.
     *
     * @param array<int, class-string> $classNames
     */
    public function collect(array $classNames, string $handle): ComponentCollection;

    /**
     * Discover a ComponentDefinition from a single class.
     *
     * @param class-string|string $className
     */
    public function discoverFromClass(string $className): ?ComponentDefinition;
}
