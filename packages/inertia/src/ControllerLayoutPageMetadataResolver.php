<?php

declare(strict_types=1);

namespace Marko\Inertia;

use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

readonly class ControllerLayoutPageMetadataResolver
{
    public function __construct(
        private ModuleRepositoryInterface $modules,
        private ProjectPaths $paths,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(
        ?string $controller,
        ?string $action,
    ): array {
        if (! is_string($controller) || $controller === '' || ! is_string($action) || $action === '') {
            return [];
        }

        $layoutAttributeClass = 'Marko\\Layout\\Attributes\\Layout';

        $layoutComponent = $this->resolveLayoutComponent(
            $controller,
            $action,
            $layoutAttributeClass,
        );

        if ($layoutComponent === null) {
            return [];
        }

        return [
            'props' => [
                '_marko' => [
                    'layout' => array_filter([
                        'component' => $layoutComponent,
                        'name' => $this->resolveClientLayoutName($layoutComponent),
                    ], static fn (mixed $value): bool => is_string($value) && $value !== ''),
                ],
            ],
        ];
    }

    private function resolveLayoutComponent(
        string $controller,
        string $action,
        string $layoutAttributeClass,
    ): ?string {
        try {
            $methodReflection = new ReflectionMethod($controller, $action);
            $classReflection = new ReflectionClass($controller);
        } catch (ReflectionException) {
            return null;
        }

        $attributes = $methodReflection->getAttributes($layoutAttributeClass);

        if ($attributes === []) {
            $attributes = $classReflection->getAttributes($layoutAttributeClass);
        }

        if ($attributes === []) {
            return null;
        }

        $arguments = $attributes[0]->getArguments();
        $component = $arguments['component'] ?? $arguments[0] ?? null;

        return is_string($component) ? $component : null;
    }

    private function resolveClientLayoutName(
        string $layoutComponent,
    ): ?string {
        try {
            $reflection = new ReflectionClass($layoutComponent);
        } catch (ReflectionException) {
            return null;
        }

        $fileName = $reflection->getFileName();

        if (! is_string($fileName) || $fileName === '') {
            return null;
        }

        $moduleName = $this->resolveModuleNameForPath($fileName);
        $shortName = $reflection->getShortName();

        return $moduleName === null ? $shortName : sprintf('%s::%s', $moduleName, $shortName);
    }

    private function resolveModuleNameForPath(
        string $path,
    ): ?string {
        $normalizedPath = $this->normalizePath($path);
        $matches = [];

        foreach ($this->modules->all() as $module) {
            $modulePath = $this->normalizePath($module->path);

            if ($modulePath !== '' && str_starts_with($normalizedPath, $modulePath . '/')) {
                $matches[] = $module;
            }
        }

        usort(
            $matches,
            static fn (object $left, object $right): int => strlen((string) $right->path) <=> strlen(
                (string) $left->path
            ),
        );

        if ($matches !== []) {
            return $this->moduleAliasName($matches[0]->name);
        }

        $appPath = $this->normalizePath($this->paths->app);

        if ($appPath !== '' && str_starts_with($normalizedPath, $appPath . '/')) {
            $relativePath = substr($normalizedPath, strlen($appPath) + 1);
            $parts = explode('/', $relativePath);

            return $parts[0] ?? null;
        }

        return null;
    }

    private function normalizePath(
        string $path,
    ): string {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function moduleAliasName(
        string $moduleName,
    ): string {
        $parts = explode('/', trim(str_replace('\\', '/', $moduleName), '/'));

        return end($parts) ?: $moduleName;
    }
}
