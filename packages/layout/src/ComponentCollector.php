<?php

declare(strict_types=1);

namespace Marko\Layout;

use Error;
use Marko\Core\Exceptions\MarkoException;
use Marko\Layout\Attributes\Component;
use Marko\Routing\RouteCollection;
use ReflectionClass;
use ReflectionException;

readonly class ComponentCollector implements ComponentCollectorInterface
{
    public function __construct(
        private HandleResolver $handleResolver,
        private RouteCollection $routeCollection,
    ) {}

    /**
     * Collect all components from the given class names that match the given handle.
     *
     * @param array<int, class-string> $classNames
     * @throws Error
     * @throws ReflectionException
     * @throws Exceptions\DuplicateComponentException
     */
    public function collect(array $classNames, string $handle): ComponentCollection
    {
        $collection = new ComponentCollection();

        foreach ($classNames as $className) {
            $definition = $this->discoverFromClass($className);

            if ($definition === null) {
                continue;
            }

            $matches = array_any(
                $definition->handles,
                fn (string $componentHandle): bool => $this->handleResolver->matches($componentHandle, $handle),
            );

            if ($matches) {
                $collection->add($definition);
            }
        }

        return $collection;
    }

    /**
     * Discover a ComponentDefinition from a single class.
     *
     * @param class-string|string $className
     * @throws Error
     * @throws ReflectionException
     */
    public function discoverFromClass(string $className): ?ComponentDefinition
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (Error $e) {
            $missingClass = MarkoException::extractMissingClass($e);
            if ($missingClass !== null && MarkoException::inferPackageName($missingClass) !== null) {
                return null;
            }
            throw $e;
        } catch (ReflectionException $e) {
            // Class doesn't exist — infer package from the class name itself
            if (MarkoException::inferPackageName($className) !== null) {
                return null;
            }
            throw $e;
        }

        $attributes = $reflection->getAttributes(Component::class);

        if ($attributes === []) {
            return null;
        }

        try {
            $attribute = $attributes[0]->newInstance();
        } catch (Error $e) {
            $missingClass = MarkoException::extractMissingClass($e);
            if ($missingClass !== null && MarkoException::inferPackageName($missingClass) !== null) {
                return null;
            }
            throw $e;
        }

        $handles = $this->resolveHandles($attribute->handle);

        return new ComponentDefinition(
            className: $className,
            template: $attribute->template,
            slot: $attribute->slot,
            handles: $handles,
            slots: $attribute->slots,
            sortOrder: $attribute->sortOrder,
            before: $attribute->before,
            after: $attribute->after,
        );
    }

    /**
     * Resolve handles from a string or array, including class-reference handles.
     *
     * @param string|array<mixed> $handle
     * @return array<int, string>
     */
    private function resolveHandles(string|array $handle): array
    {
        if (is_string($handle)) {
            return [$handle];
        }

        // Check if it's a class-reference handle: [ClassName::class, 'method']
        if (
            count($handle) === 2
            && is_string($handle[0])
            && is_string($handle[1])
            && class_exists($handle[0])
        ) {
            $resolved = $this->resolveClassReferenceHandle($handle[0], $handle[1]);
            if ($resolved !== null) {
                return [$resolved];
            }
        }

        /** @var array<int, string> $handles */
        $handles = $handle;

        return $handles;
    }

    /**
     * Resolve a class-reference handle to a string handle via RouteCollection.
     */
    private function resolveClassReferenceHandle(string $controllerClass, string $action): ?string
    {
        foreach ($this->routeCollection->all() as $route) {
            if ($route->controller === $controllerClass && $route->action === $action) {
                return $this->handleResolver->generate($route->path, $controllerClass, $action);
            }
        }

        return null;
    }
}
