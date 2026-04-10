<?php

declare(strict_types=1);

namespace Marko\Layout;

use Marko\Layout\Attributes\Component;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\Exceptions\LayoutNotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

readonly class LayoutResolver
{
    /**
     * Resolve the layout component for a controller action.
     *
     * Method-level #[Layout] takes priority over class-level.
     * The referenced component class must have a #[Component] attribute.
     *
     * @param class-string $controllerClass
     * @return array{componentClass: class-string, attribute: Component}
     * @throws LayoutNotFoundException
     * @throws ReflectionException
     */
    public function resolve(string $controllerClass, string $method): array
    {
        $classReflection = new ReflectionClass($controllerClass);
        $methodReflection = new ReflectionMethod($controllerClass, $method);

        $layout = $this->getMethodLayout($methodReflection)
            ?? $this->getClassLayout($classReflection);

        if ($layout === null) {
            throw new LayoutNotFoundException(
                message: "No #[Layout] attribute found on controller '$controllerClass'.",
                context: "Attempted to resolve a layout for '$controllerClass::$method' but neither the class nor the method has a #[Layout] attribute.",
                suggestion: "Add a #[Layout(component: YourRootComponent::class)] attribute to the controller class or the '$method' method.",
            );
        }

        $componentClass = $layout->component;
        $componentReflection = new ReflectionClass($componentClass);
        $componentAttributes = $componentReflection->getAttributes(Component::class);

        if ($componentAttributes === []) {
            throw new LayoutNotFoundException(
                message: "Component class '$componentClass' has no #[Component] attribute.",
                context: "The layout on '$controllerClass::$method' references '$componentClass', which is not a valid component.",
                suggestion: "Add a #[Component(template: 'your-template.html')] attribute to the '$componentClass' class.",
            );
        }

        $componentAttribute = $componentAttributes[0]->newInstance();

        return [
            'componentClass' => $componentClass,
            'attribute' => $componentAttribute,
        ];
    }

    private function getMethodLayout(ReflectionMethod $method): ?Layout
    {
        $attributes = $method->getAttributes(Layout::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function getClassLayout(ReflectionClass $class): ?Layout
    {
        $attributes = $class->getAttributes(Layout::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
