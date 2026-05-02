<?php

declare(strict_types=1);

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\Discovery\AdminPermissionDefinition;
use Marko\Admin\Discovery\AdminSectionDefinition;
use Marko\Admin\Discovery\AdminSectionDiscovery;
use Marko\Admin\Exceptions\AdminException;
use Marko\Core\Module\ModuleManifest;

// Helper function for recursive directory cleanup
function cleanupAdminTestDirectory(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            cleanupAdminTestDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

it('discovers classes with AdminSection attribute in a module', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-admin-discovery-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/src', 0755, true);

    $classCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTest1;

use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;

#[AdminSection(id: 'catalog', label: 'Catalog', icon: 'box', sortOrder: 10)]
class CatalogSection implements AdminSectionInterface
{
    public function getId(): string { return 'catalog'; }
    public function getLabel(): string { return 'Catalog'; }
    public function getIcon(): string { return 'box'; }
    public function getSortOrder(): int { return 10; }
    /** @return array<MenuItemInterface> */
    public function getMenuItems(): array { return []; }
}
PHP;
    file_put_contents($tempDir . '/src/CatalogSection.php', $classCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new AdminSectionDiscovery();
    $files = $discovery->discoverInModule($manifest);

    expect($files)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($files[0])->toEndWith('CatalogSection.php');

    cleanupAdminTestDirectory($tempDir);
});

it('skips classes without AdminSection attribute', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-admin-discovery-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/src', 0755, true);

    $classCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTest2;

class SomeHelper
{
    public function help(): void {}
}
PHP;
    file_put_contents($tempDir . '/src/SomeHelper.php', $classCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new AdminSectionDiscovery();
    $files = $discovery->discoverInModule($manifest);

    expect($files)
        ->toBeArray()
        ->toBeEmpty();

    cleanupAdminTestDirectory($tempDir);
});

it('throws AdminException when AdminSection class does not implement AdminSectionInterface', function (): void {
    $discovery = new AdminSectionDiscovery();

    expect(fn () => $discovery->parseAdminSectionClass(InvalidAdminSectionNoInterface::class))
        ->toThrow(AdminException::class, 'does not implement AdminSectionInterface');
});

it('extracts section metadata from AdminSection attribute', function (): void {
    $discovery = new AdminSectionDiscovery();
    $definition = $discovery->parseAdminSectionClass(ValidAdminSection::class);

    expect($definition)
        ->toBeInstanceOf(AdminSectionDefinition::class)
        ->and($definition->className)->toBe(ValidAdminSection::class)
        ->and($definition->id)->toBe('sales')
        ->and($definition->label)->toBe('Sales')
        ->and($definition->icon)->toBe('dollar-sign')
        ->and($definition->sortOrder)->toBe(20);
});

it('discovers AdminPermission attributes on AdminSection classes', function (): void {
    $discovery = new AdminSectionDiscovery();
    $definition = $discovery->parseAdminSectionClass(AdminSectionWithPermissions::class);

    expect($definition->permissions)
        ->toBeArray()
        ->toHaveCount(2)
        ->and($definition->permissions[0])
        ->toBeInstanceOf(AdminPermissionDefinition::class)
        ->and($definition->permissions[0]->id)->toBe('sales.view')
        ->and($definition->permissions[0]->label)->toBe('View Sales')
        ->and($definition->permissions[1]->id)->toBe('sales.edit')
        ->and($definition->permissions[1]->label)->toBe('Edit Sales');
});

it('returns empty array when module has no admin sections', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-admin-discovery-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/src', 0755, true);

    // Create a PHP file without AdminSection attribute
    $classCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTest3;

class UnrelatedService
{
    public function execute(): void {}
}
PHP;
    file_put_contents($tempDir . '/src/UnrelatedService.php', $classCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new AdminSectionDiscovery();
    $files = $discovery->discoverInModule($manifest);

    expect($files)->toBeArray()
        ->and($files)->toBeEmpty();

    cleanupAdminTestDirectory($tempDir);
});

it('discovers sections across multiple modules', function (): void {
    $tempDir1 = sys_get_temp_dir() . '/marko-admin-discovery-test-' . bin2hex(random_bytes(8));
    $tempDir2 = sys_get_temp_dir() . '/marko-admin-discovery-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir1 . '/src', 0755, true);
    mkdir($tempDir2 . '/src', 0755, true);

    $classCode1 = <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTestMulti1;

use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;

#[AdminSection(id: 'catalog', label: 'Catalog', icon: 'box', sortOrder: 10)]
class CatalogSection implements AdminSectionInterface
{
    public function getId(): string { return 'catalog'; }
    public function getLabel(): string { return 'Catalog'; }
    public function getIcon(): string { return 'box'; }
    public function getSortOrder(): int { return 10; }
    /** @return array<MenuItemInterface> */
    public function getMenuItems(): array { return []; }
}
PHP;

    $classCode2 = <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTestMulti2;

use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;

#[AdminSection(id: 'sales', label: 'Sales', icon: 'dollar', sortOrder: 20)]
class SalesSection implements AdminSectionInterface
{
    public function getId(): string { return 'sales'; }
    public function getLabel(): string { return 'Sales'; }
    public function getIcon(): string { return 'dollar'; }
    public function getSortOrder(): int { return 20; }
    /** @return array<MenuItemInterface> */
    public function getMenuItems(): array { return []; }
}
PHP;

    file_put_contents($tempDir1 . '/src/CatalogSection.php', $classCode1);
    file_put_contents($tempDir2 . '/src/SalesSection.php', $classCode2);

    $manifest1 = new ModuleManifest(
        name: 'test/catalog',
        version: '1.0.0',
        path: $tempDir1,
    );
    $manifest2 = new ModuleManifest(
        name: 'test/sales',
        version: '1.0.0',
        path: $tempDir2,
    );

    $discovery = new AdminSectionDiscovery();

    $files1 = $discovery->discoverInModule($manifest1);
    $files2 = $discovery->discoverInModule($manifest2);
    $allFiles = array_merge($files1, $files2);

    expect($allFiles)
        ->toBeArray()
        ->toHaveCount(2)
        ->and($allFiles[0])->toEndWith('CatalogSection.php')
        ->and($allFiles[1])->toEndWith('SalesSection.php');

    cleanupAdminTestDirectory($tempDir1);
    cleanupAdminTestDirectory($tempDir2);
});

// Test fixture classes for reflection-based tests

#[AdminSection(id: 'invalid', label: 'Invalid')]
class InvalidAdminSectionNoInterface
{
    // Missing AdminSectionInterface implementation
}

#[AdminSection(id: 'sales', label: 'Sales', icon: 'dollar-sign', sortOrder: 20)]
class ValidAdminSection implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'sales';
    }

    public function getLabel(): string
    {
        return 'Sales';
    }

    public function getIcon(): string
    {
        return 'dollar-sign';
    }

    public function getSortOrder(): int
    {
        return 20;
    }

    /** @return array<MenuItemInterface> */
    public function getMenuItems(): array
    {
        return [];
    }
}

#[AdminSection(id: 'sales-perms', label: 'Sales', icon: 'dollar-sign', sortOrder: 20)]
#[AdminPermission(id: 'sales.view', label: 'View Sales')]
#[AdminPermission(id: 'sales.edit', label: 'Edit Sales')]
class AdminSectionWithPermissions implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'sales-perms';
    }

    public function getLabel(): string
    {
        return 'Sales';
    }

    public function getIcon(): string
    {
        return 'dollar-sign';
    }

    public function getSortOrder(): int
    {
        return 20;
    }

    /** @return array<MenuItemInterface> */
    public function getMenuItems(): array
    {
        return [];
    }
}
