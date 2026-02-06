<?php

declare(strict_types=1);

namespace Marko\Admin\Discovery;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Exceptions\AdminException;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;

class AdminSectionDiscovery
{
    public function __construct(
        private readonly ClassFileParser $classFileParser,
    ) {}

    /**
     * Discover files containing AdminSection attribute in a module's src directory.
     *
     * @return array<string> List of absolute paths to PHP files containing admin sections
     */
    public function discoverInModule(
        ModuleManifest $manifest,
    ): array {
        $srcDir = $manifest->path . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $sectionFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir),
        );
        $phpFiles = new RegexIterator($iterator, '/\.php$/');

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file->getPathname());
            if ($content !== false && str_contains($content, '#[AdminSection')) {
                $sectionFiles[] = $file->getPathname();
            }
        }

        return $sectionFiles;
    }

    /**
     * Parse a class with AdminSection attribute into a definition.
     *
     * @param class-string $className
     * @throws AdminException When the class does not implement AdminSectionInterface
     */
    public function parseAdminSectionClass(
        string $className,
    ): AdminSectionDefinition {
        $reflection = new ReflectionClass($className);

        if (!$reflection->implementsInterface(AdminSectionInterface::class)) {
            throw AdminException::sectionMustImplementInterface($className);
        }

        $sectionAttributes = $reflection->getAttributes(AdminSection::class);
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
