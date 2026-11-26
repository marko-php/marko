<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Subject;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

class PluginDiscovery
{
    /**
     * Discover plugin definitions in a module's src directory.
     *
     * Scans PHP files for classes with the #[Plugin] attribute and returns
     * fully parsed PluginDefinition instances ready for registration.
     *
     * @return array<PluginDefinition>
     * @throws PluginException|ReflectionException
     */
    public function discoverInModule(
        ModuleManifest $manifest,
    ): array {
        $srcDir = $manifest->path . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $definitions = [];
        $parser = new ClassFileParser();

        foreach ($parser->findPhpFiles($srcDir) as $file) {
            $filepath = $file->getPathname();

            // Cheap pre-filter: skip files that obviously don't declare a plugin
            // before paying for class extraction, autoload, and reflection.
            $contents = file_get_contents($filepath);
            if (
                $contents === false
                || !str_contains($contents, '#[Plugin')
            ) {
                continue;
            }

            $className = $parser->extractClassName($filepath);
            if ($className === null) {
                continue;
            }

            if (!$parser->loadClass($filepath, $className)) {
                continue;
            }

            $reflector = new ReflectionClass($className);
            $pluginAttributes = $reflector->getAttributes(Plugin::class);

            if (empty($pluginAttributes)) {
                continue;
            }

            $definitions[] = $this->parsePluginClass($className);
        }

        return $definitions;
    }

    /**
     * Parse a plugin class and extract its definition.
     *
     * @param class-string $pluginClass
     * @throws PluginException|ReflectionException
     */
    public function parsePluginClass(
        string $pluginClass,
    ): PluginDefinition {
        $reflection = new ReflectionClass($pluginClass);
        $pluginAttributes = $reflection->getAttributes(Plugin::class);

        if (empty($pluginAttributes)) {
            throw PluginException::missingPluginAttribute($pluginClass);
        }

        $pluginAttribute = $pluginAttributes[0]->newInstance();

        $beforeMethods = [];
        $afterMethods = [];
        /** @var array<string, list<string>> $beforeTargets */
        $beforeTargets = [];
        /** @var array<string, list<string>> $afterTargets */
        $afterTargets = [];

        foreach ($reflection->getMethods() as $method) {
            $pluginMethodName = $method->getName();

            $this->validateSubjectParameters($pluginClass, $method, $pluginAttribute->target);

            $beforeAttributes = $method->getAttributes(Before::class);
            if (!empty($beforeAttributes)) {
                $beforeAttribute = $beforeAttributes[0]->newInstance();
                $targetMethodName = $beforeAttribute->method ?? $pluginMethodName;
                $beforeTargets[$targetMethodName][] = $pluginMethodName;
                $beforeMethods[$targetMethodName] = [
                    'pluginMethod' => $pluginMethodName,
                    'sortOrder' => $beforeAttribute->sortOrder,
                    'subject' => $this->takesSubject($method),
                ];
            }

            $afterAttributes = $method->getAttributes(After::class);
            if (!empty($afterAttributes)) {
                $afterAttribute = $afterAttributes[0]->newInstance();
                $targetMethodName = $afterAttribute->method ?? $pluginMethodName;
                $afterTargets[$targetMethodName][] = $pluginMethodName;
                $afterMethods[$targetMethodName] = [
                    'pluginMethod' => $pluginMethodName,
                    'sortOrder' => $afterAttribute->sortOrder,
                    'subject' => $this->takesSubject($method),
                ];
            }
        }

        foreach ($beforeTargets as $targetMethodName => $pluginMethods) {
            if (count($pluginMethods) > 1) {
                throw PluginException::duplicatePluginHook($pluginClass, 'before', $targetMethodName, $pluginMethods);
            }
        }

        foreach ($afterTargets as $targetMethodName => $pluginMethods) {
            if (count($pluginMethods) > 1) {
                throw PluginException::duplicatePluginHook($pluginClass, 'after', $targetMethodName, $pluginMethods);
            }
        }

        return new PluginDefinition(
            pluginClass: $pluginClass,
            targetClass: $pluginAttribute->target,
            beforeMethods: $beforeMethods,
            afterMethods: $afterMethods,
        );
    }

    /**
     * Validate that a class with #[Before]/#[After] methods also has #[Plugin] attribute.
     *
     * @param class-string $className
     * @throws PluginException|ReflectionException
     */
    public function validatePluginMethods(
        string $className,
    ): void {
        $reflection = new ReflectionClass($className);
        $hasPluginAttribute = !empty($reflection->getAttributes(Plugin::class));

        if ($hasPluginAttribute) {
            return;
        }

        $orphanedMethods = [];
        foreach ($reflection->getMethods() as $method) {
            $hasBeforeAttribute = !empty($method->getAttributes(Before::class));
            $hasAfterAttribute = !empty($method->getAttributes(After::class));

            if (
                $hasBeforeAttribute
                || $hasAfterAttribute
            ) {
                $orphanedMethods[] = $method->getName();
            }
        }

        if (!empty($orphanedMethods)) {
            throw PluginException::orphanedPluginMethods($className, $orphanedMethods);
        }
    }

    /**
     * @param class-string $pluginClass
     * @throws PluginException
     */
    private function validateSubjectParameters(
        string $pluginClass,
        ReflectionMethod $method,
        string $targetClass,
    ): void {
        $isHook = !empty($method->getAttributes(Before::class))
            || !empty($method->getAttributes(After::class));

        foreach ($method->getParameters() as $parameter) {
            if (empty($parameter->getAttributes(Subject::class))) {
                continue;
            }

            if (!$isHook) {
                throw PluginException::subjectOnNonHookMethod(
                    $pluginClass,
                    $method->getName(),
                    $parameter->getName(),
                    $parameter->getPosition(),
                );
            }

            if ($parameter->getPosition() > 0) {
                throw PluginException::subjectNotFirstParameter(
                    $pluginClass,
                    $method->getName(),
                    $parameter->getName(),
                    $parameter->getPosition(),
                );
            }

            if (
                $parameter->isVariadic()
                || $parameter->isPassedByReference()
            ) {
                throw PluginException::subjectNotPlainParameter(
                    $pluginClass,
                    $method->getName(),
                    $parameter->getName(),
                    $parameter->getPosition(),
                );
            }

            $this->validateSubjectType($pluginClass, $method, $parameter, $targetClass);
        }
    }

    /**
     * @param class-string $pluginClass
     * @throws PluginException
     */
    private function validateSubjectType(
        string $pluginClass,
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        string $targetClass,
    ): void {
        $type = $parameter->getType();

        if (
            $type === null
            || !$this->targetExists($targetClass)
        ) {
            return;
        }

        if ($this->typeCanHoldTarget($type, $pluginClass, $targetClass)) {
            return;
        }

        throw PluginException::subjectTypeMismatch(
            $pluginClass,
            $method->getName(),
            $parameter->getName(),
            (string) $type,
            $targetClass,
        );
    }

    private function targetExists(
        string $targetClass,
    ): bool {
        return class_exists($targetClass)
            || interface_exists($targetClass);
    }

    private function typeCanHoldTarget(
        ReflectionType $type,
        string $pluginClass,
        string $targetClass,
    ): bool {
        if ($type instanceof ReflectionUnionType) {
            return array_any(
                $type->getTypes(),
                fn (ReflectionType $member): bool => $this->typeCanHoldTarget($member, $pluginClass, $targetClass),
            );
        }

        if ($type instanceof ReflectionIntersectionType) {
            return array_all(
                $type->getTypes(),
                fn (ReflectionType $member): bool => $this->typeCanHoldTarget($member, $pluginClass, $targetClass),
            );
        }

        if (
            $type instanceof ReflectionNamedType
            && $type->isBuiltin()
        ) {
            return in_array($type->getName(), ['object', 'mixed'], true);
        }

        return $type instanceof ReflectionNamedType
            && is_a($targetClass, $this->resolveTypeName($type->getName(), $pluginClass), true);
    }

    private function resolveTypeName(
        string $typeName,
        string $pluginClass,
    ): string {
        return match ($typeName) {
            'self' => $pluginClass,
            'parent' => get_parent_class($pluginClass) ?: $typeName,
            default => $typeName,
        };
    }

    private function takesSubject(
        ReflectionMethod $method,
    ): bool {
        $firstParameter = $method->getParameters()[0] ?? null;

        return $firstParameter !== null
            && !empty($firstParameter->getAttributes(Subject::class));
    }
}
