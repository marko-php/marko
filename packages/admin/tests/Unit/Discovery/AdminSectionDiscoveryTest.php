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
        if (
            $item === '.'
            || $item === '..'
        ) {
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
        ->and($files[0])->toBe('AdminDiscoveryTest1\\CatalogSection');

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
        ->and($allFiles[0])->toBe('AdminDiscoveryTestMulti1\\CatalogSection')
        ->and($allFiles[1])->toBe('AdminDiscoveryTestMulti2\\SalesSection');

    cleanupAdminTestDirectory($tempDir1);
    cleanupAdminTestDirectory($tempDir2);
});

it('throws AdminException naming the class when the class has no AdminSection attribute', function (): void {
    $discovery = new AdminSectionDiscovery();

    expect(fn () => $discovery->parseAdminSectionClass(SectionInterfaceWithoutAttribute::class))
        ->toThrow(
            AdminException::class,
            "Class '" . SectionInterfaceWithoutAttribute::class . "' is not marked with #[AdminSection]",
        );
});

it('suggests adding the AdminSection attribute when the attribute is missing', function (): void {
    $discovery = new AdminSectionDiscovery();

    try {
        $discovery->parseAdminSectionClass(SectionInterfaceWithoutAttribute::class);
        $this->fail('Expected AdminException was not thrown');
    } catch (AdminException $e) {
        expect($e->getContext())
            ->toBe("While parsing admin section class '" . SectionInterfaceWithoutAttribute::class . "'")
            ->and($e->getSuggestion())
            ->toBe(
                "Add #[AdminSection(id: ..., label: ...)] to the class, or don't pass it to admin section discovery",
            );
    }
});

it('reports the missing attribute before the missing interface when a class has neither', function (): void {
    $discovery = new AdminSectionDiscovery();

    expect(fn () => $discovery->parseAdminSectionClass(PlainClassWithoutAttribute::class))
        ->toThrow(AdminException::class, 'is not marked with #[AdminSection]');
});

it('still throws the interface exception for a class with the attribute but without the interface', function (): void {
    $discovery = new AdminSectionDiscovery();

    expect(fn () => $discovery->parseAdminSectionClass(InvalidAdminSectionNoInterface::class))
        ->toThrow(
            AdminException::class,
            "Class '" . InvalidAdminSectionNoInterface::class . "' has #[AdminSection] attribute but does not implement AdminSectionInterface",
        );
});

/**
 * Write PHP files into a fresh temporary module and return its manifest.
 *
 * @param array<string, string> $files File name => file contents
 */
function createAdminTestModule(
    array $files,
): ModuleManifest {
    $tempDir = sys_get_temp_dir() . '/marko-admin-discovery-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/src', 0755, true);

    foreach ($files as $name => $contents) {
        file_put_contents($tempDir . '/src/' . $name, $contents);
    }

    return new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );
}

it('skips files that mention AdminSection only in a comment', function (): void {
    $manifest = createAdminTestModule([
        'CommentedHelper.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTestComment;

/**
 * Not a section. Sections are marked with #[AdminSection(id: ..., label: ...)].
 */
class CommentedHelper
{
    // #[AdminSection(id: 'old', label: 'Old')]
    public function help(): void {}
}
PHP,
    ]);

    $files = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($files)->toBe([]);

    cleanupAdminTestDirectory($manifest->path);
});

it('skips files whose attribute name only starts with AdminSection', function (): void {
    $manifest = createAdminTestModule([
        'AdminSectionWidget.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTestPrefix;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AdminSectionWidget {}
PHP,
        'SalesWidget.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTestPrefix;

#[AdminSectionWidget]
class SalesWidget {}
PHP,
    ]);

    $files = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($files)->toBe([]);

    cleanupAdminTestDirectory($manifest->path);
});

it('skips files that declare no class', function (): void {
    $manifest = createAdminTestModule([
        'notes.php' => <<<'PHP'
<?php

declare(strict_types=1);

// Remember to add #[AdminSection] to new sections.
return [];
PHP,
    ]);

    $files = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($files)->toBe([]);

    cleanupAdminTestDirectory($manifest->path);
});

it('still reports a file whose class has the attribute but does not implement the interface', function (): void {
    $manifest = createAdminTestModule([
        'BrokenSection.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTestBroken;

use Marko\Admin\Attributes\AdminSection;

#[AdminSection(id: 'broken', label: 'Broken')]
class BrokenSection {}
PHP,
    ]);

    $files = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($files)
        ->toHaveCount(1)
        ->and($files[0])->toBe('AdminDiscoveryTestBroken\\BrokenSection');

    cleanupAdminTestDirectory($manifest->path);
});

/**
 * PHP source for a valid admin section class.
 */
function adminTestSectionSource(
    string $namespace,
    string $className,
    string $id,
    string $attribute = '#[AdminSection(id: \'%s\', label: \'%s\')]',
    string $imports = "use Marko\\Admin\\Attributes\\AdminSection;\n",
): string {
    $attributeLine = sprintf($attribute, $id, ucfirst($id));

    return <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

{$imports}use Marko\\Admin\\Contracts\\AdminSectionInterface;

$attributeLine
class $className implements AdminSectionInterface
{
    public function getId(): string { return '$id'; }
    public function getLabel(): string { return '$id'; }
    public function getIcon(): string { return ''; }
    public function getSortOrder(): int { return 0; }
    public function getMenuItems(): array { return []; }
}
PHP;
}

it('discovers every admin section class in a file that declares several classes', function (): void {
    $manifest = createAdminTestModule([
        'Sections.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace AdminDiscoveryTestSeveral;

use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;

class SectionHelper {}

#[AdminSection(id: 'orders', label: 'Orders')]
class OrdersSection implements AdminSectionInterface
{
    public function getId(): string { return 'orders'; }
    public function getLabel(): string { return 'Orders'; }
    public function getIcon(): string { return ''; }
    public function getSortOrder(): int { return 0; }
    public function getMenuItems(): array { return []; }
}

#[AdminSection(id: 'invoices', label: 'Invoices')]
class InvoicesSection implements AdminSectionInterface
{
    public function getId(): string { return 'invoices'; }
    public function getLabel(): string { return 'Invoices'; }
    public function getIcon(): string { return ''; }
    public function getSortOrder(): int { return 0; }
    public function getMenuItems(): array { return []; }
}
PHP,
    ]);

    $classNames = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($classNames)->toBe([
        'AdminDiscoveryTestSeveral\\OrdersSection',
        'AdminDiscoveryTestSeveral\\InvoicesSection',
    ]);

    cleanupAdminTestDirectory($manifest->path);
});

it('discovers a section marked with the fully-qualified attribute name', function (): void {
    $manifest = createAdminTestModule([
        'QualifiedSection.php' => adminTestSectionSource(
            namespace: 'AdminDiscoveryTestQualified',
            className: 'QualifiedSection',
            id: 'qualified',
            attribute: '#[\\Marko\\Admin\\Attributes\\AdminSection(id: \'%s\', label: \'%s\')]',
            imports: '',
        ),
    ]);

    $classNames = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($classNames)->toBe(['AdminDiscoveryTestQualified\\QualifiedSection']);

    cleanupAdminTestDirectory($manifest->path);
});

it('discovers a section marked with an aliased attribute import', function (): void {
    $manifest = createAdminTestModule([
        'AliasedSection.php' => adminTestSectionSource(
            namespace: 'AdminDiscoveryTestAliased',
            className: 'AliasedSection',
            id: 'aliased',
            attribute: '#[Section(id: \'%s\', label: \'%s\')]',
            imports: "use Marko\\Admin\\Attributes\\AdminSection as Section;\n",
        ),
    ]);

    $classNames = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($classNames)->toBe(['AdminDiscoveryTestAliased\\AliasedSection']);

    cleanupAdminTestDirectory($manifest->path);
});

it('discovers a section whose attribute is grouped with another attribute', function (): void {
    $manifest = createAdminTestModule([
        'GroupedSection.php' => adminTestSectionSource(
            namespace: 'AdminDiscoveryTestGrouped',
            className: 'GroupedSection',
            id: 'grouped',
            attribute: '#[AdminPermission(id: \'grouped.view\'), AdminSection(id: \'%s\', label: \'%s\')]',
            imports: "use Marko\\Admin\\Attributes\\AdminPermission;\nuse Marko\\Admin\\Attributes\\AdminSection;\n",
        ),
    ]);

    $classNames = (new AdminSectionDiscovery())->discoverInModule($manifest);

    expect($classNames)->toBe(['AdminDiscoveryTestGrouped\\GroupedSection']);

    cleanupAdminTestDirectory($manifest->path);
});

it('parses every discovered section across modules in module order', function (): void {
    $first = createAdminTestModule([
        'BillingSection.php' => adminTestSectionSource('AdminDiscoveryTestAllFirst', 'BillingSection', 'billing'),
    ]);
    $second = createAdminTestModule([
        'ReportsSection.php' => adminTestSectionSource('AdminDiscoveryTestAllSecond', 'ReportsSection', 'reports'),
    ]);

    $definitions = (new AdminSectionDiscovery())->discoverAll([$first, $second]);

    expect(array_map(fn (AdminSectionDefinition $definition): string => $definition->className, $definitions))
        ->toBe(['AdminDiscoveryTestAllFirst\\BillingSection', 'AdminDiscoveryTestAllSecond\\ReportsSection'])
        ->and(array_map(fn (AdminSectionDefinition $definition): string => $definition->id, $definitions))
        ->toBe(['billing', 'reports']);

    cleanupAdminTestDirectory($first->path);
    cleanupAdminTestDirectory($second->path);
});

it('throws duplicateSection naming both classes when two sections share an id', function (): void {
    $first = createAdminTestModule([
        'TeamSection.php' => adminTestSectionSource('AdminDiscoveryTestDupFirst', 'TeamSection', 'team'),
    ]);
    $second = createAdminTestModule([
        'TeamSection.php' => adminTestSectionSource('AdminDiscoveryTestDupSecond', 'TeamSection', 'team'),
    ]);

    try {
        expect(fn () => (new AdminSectionDiscovery())->discoverAll([$first, $second]))
            ->toThrow(
                AdminException::class,
                "Admin section with id 'team' is declared by both 'AdminDiscoveryTestDupFirst\\TeamSection'"
                . " and 'AdminDiscoveryTestDupSecond\\TeamSection'",
            );
    } finally {
        cleanupAdminTestDirectory($first->path);
        cleanupAdminTestDirectory($second->path);
    }
});

// Test fixture classes for reflection-based tests

class SectionInterfaceWithoutAttribute implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'unmarked';
    }

    public function getLabel(): string
    {
        return 'Unmarked';
    }

    public function getIcon(): string
    {
        return '';
    }

    public function getSortOrder(): int
    {
        return 0;
    }

    /** @return array<MenuItemInterface> */
    public function getMenuItems(): array
    {
        return [];
    }
}

class PlainClassWithoutAttribute {}

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
