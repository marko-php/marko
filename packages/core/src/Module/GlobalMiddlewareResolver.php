<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Marko\Core\Exceptions\ModuleException;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Resolves global HTTP middleware from module declarations.
 *
 * Walks modules in the order supplied (DependencyResolver topologically sorts
 * them via composer require + sequence: { after, before } before they reach
 * here) and collects each module's globalMiddleware class-strings in
 * declaration order. Each class is emitted once; a higher-source declaration
 * (app > modules > vendor) takes the position of the same class declared by a
 * lower-source module.
 */
class GlobalMiddlewareResolver
{
    private const array SOURCE_PRIORITY = [
        'vendor' => 0,
        'modules' => 1,
        'app' => 2,
    ];

    /**
     * Resolve global middleware from module declarations.
     *
     * @param array<ModuleManifest> $modules Modules in load order (already topologically sorted).
     * @return array<class-string<MiddlewareInterface>>
     * @throws ModuleException When a declared entry is not a class-string, the class does not exist, or it does not implement MiddlewareInterface.
     */
    public function resolve(array $modules): array
    {
        /** @var array<string, int> $sourceByClass */
        $sourceByClass = [];

        /** @var array<int, string> $order */
        $order = [];

        foreach ($modules as $module) {
            $moduleSource = self::SOURCE_PRIORITY[$module->source] ?? 0;

            foreach ($module->globalMiddleware as $entry) {
                $class = $this->parseEntry($entry, $module->name);
                $this->validateClass($class, $module->name);

                if (!isset($sourceByClass[$class])) {
                    $sourceByClass[$class] = $moduleSource;
                    $order[] = $class;
                    continue;
                }

                if ($moduleSource > $sourceByClass[$class]) {
                    // Higher source wins on position: drop the prior occurrence and re-append.
                    $sourceByClass[$class] = $moduleSource;
                    $order = array_values(array_filter(
                        $order,
                        fn (string $existing): bool => $existing !== $class,
                    ));
                    $order[] = $class;
                }
            }
        }

        return $order;
    }

    /**
     * @throws ModuleException
     */
    private function parseEntry(
        mixed $entry,
        string $moduleName,
    ): string {
        if (!is_string($entry)) {
            throw ModuleException::invalidMiddlewareEntry(
                moduleName: $moduleName,
                reason: 'Each globalMiddleware entry must be a class-string',
            );
        }

        return $entry;
    }

    /**
     * @throws ModuleException
     */
    private function validateClass(
        string $class,
        string $moduleName,
    ): void {
        if (!class_exists($class)) {
            throw ModuleException::invalidMiddlewareClass(
                moduleName: $moduleName,
                className: $class,
                reason: "Class '$class' does not exist",
            );
        }

        if (!is_a($class, MiddlewareInterface::class, true)) {
            throw ModuleException::invalidMiddlewareClass(
                moduleName: $moduleName,
                className: $class,
                reason: "Class '$class' does not implement " . MiddlewareInterface::class,
            );
        }
    }
}
