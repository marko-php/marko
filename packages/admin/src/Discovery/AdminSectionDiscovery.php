<?php

declare(strict_types=1);

namespace Marko\Admin\Discovery;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Exceptions\AdminException;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;
use ReflectionException;

readonly class AdminSectionDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser = new ClassFileParser(),
    ) {}

    /**
     * Parse every #[AdminSection] class across the given modules, in module order.
     *
     * @param array<ModuleManifest> $modules
     * @return array<int, AdminSectionDefinition>
     * @throws AdminException|ReflectionException
     */
    public function discoverAll(
        array $modules,
    ): array {
        /** @var array<string, AdminSectionDefinition> $definitions keyed by section id */
        $definitions = [];

        foreach ($modules as $module) {
            foreach ($this->discoverInModule($module) as $className) {
                $definition = $this->parseAdminSectionClass($className);
                $existing = $definitions[$definition->id] ?? null;

                if ($existing !== null) {
                    throw AdminException::duplicateSection($definition->id, $existing->className, $className);
                }

                $definitions[$definition->id] = $definition;
            }
        }

        return array_values($definitions);
    }

    /**
     * Discover every class marked with #[AdminSection] in a module's src directory.
     *
     * A cheap text match selects candidate files: a file must contain an attribute ("#[") and
     * mention "AdminSection" (case-insensitively, as PHP class names are), which covers the
     * imported, fully-qualified, aliased and grouped spellings of the attribute. Every class a
     * candidate declares is then loaded and confirmed with reflection, so files that only mention
     * the attribute (in a comment, or via a longer attribute name such as #[AdminSectionWidget])
     * are skipped. A class that really carries the attribute is always reported, even when it is
     * invalid, so parseAdminSectionClass() can fail loudly on it.
     *
     * @return array<int, class-string> Admin section class names, in file order
     */
    public function discoverInModule(
        ModuleManifest $manifest,
    ): array {
        $sectionClasses = [];

        foreach ($this->classFileParser->findPhpFiles($manifest->path . '/src') as $file) {
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);

            if (
                $content === false
                || !str_contains($content, '#[')
                || stripos($content, 'AdminSection') === false
            ) {
                continue;
            }

            foreach ($this->classFileParser->extractClassNames($filePath) as $className) {
                if ($this->declaresAdminSection($filePath, $className)) {
                    /** @var class-string $className */
                    $sectionClasses[] = $className;
                }
            }
        }

        return $sectionClasses;
    }

    private function declaresAdminSection(
        string $filePath,
        string $className,
    ): bool {
        if (!$this->classFileParser->loadClass($filePath, $className)) {
            return false;
        }

        /** @var class-string $className */
        return (new ReflectionClass($className))->getAttributes(AdminSection::class) !== [];
    }

    /**
     * Parse a class with AdminSection attribute into a definition.
     *
     * @param class-string $className
     * @throws AdminException|ReflectionException
     */
    public function parseAdminSectionClass(
        string $className,
    ): AdminSectionDefinition {
        $reflection = new ReflectionClass($className);
        $sectionAttributes = $reflection->getAttributes(AdminSection::class);

        if ($sectionAttributes === []) {
            throw AdminException::missingSectionAttribute($className);
        }

        if (!$reflection->implementsInterface(AdminSectionInterface::class)) {
            throw AdminException::sectionMustImplementInterface($className);
        }

        $sectionAttribute = $sectionAttributes[0]->newInstance();

        $permissions = [];
        $permissionAttributes = $reflection->getAttributes(AdminPermission::class);
        foreach ($permissionAttributes as $permissionAttribute) {
            $permission = $permissionAttribute->newInstance();
            $permissions[] = new AdminPermissionDefinition(
                id: $permission->id,
                label: $permission->label,
            );
        }

        return new AdminSectionDefinition(
            className: $className,
            id: $sectionAttribute->id,
            label: $sectionAttribute->label,
            icon: $sectionAttribute->icon,
            sortOrder: $sectionAttribute->sortOrder,
            permissions: $permissions,
        );
    }
}
