<?php

declare(strict_types=1);

namespace Marko\Layout\Tests\Unit;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Layout\Attributes\Component;
use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentCollector;
use Marko\Layout\ComponentDefinition;
use Marko\Layout\DiscoveringComponentCollector;
use Marko\Layout\HandleResolver;
use Marko\Routing\RouteCollection;

// --- Fixture classes for discovery tests ---

#[Component(template: 'discoverable/component.phtml', handle: 'test_handle')]
class DiscoverableComponent {}

class NonComponentClass {}

// --- Helper to build a fake ModuleRepositoryInterface ---

function makeModuleRepository(array $modules): ModuleRepositoryInterface
{
    return new class ($modules) implements ModuleRepositoryInterface
    {
        public function __construct(private array $modules) {}

        public function all(): array
        {
            return $this->modules;
        }
    };
}

// --- Helper to build the inner ComponentCollector ---

function makeInnerCollector(): ComponentCollector
{
    return new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );
}

// --- Tests ---

it('discovers classes with Component attribute from module src directories', function (): void {
    // Create a temp module with a src/ dir containing a PHP file
    $tmpDir = sys_get_temp_dir() . '/marko-layout-test-' . bin2hex(random_bytes(8));
    $srcDir = $tmpDir . '/src';
    mkdir($srcDir, 0755, true);

    // Write a PHP file with a Component-attributed class
    file_put_contents($srcDir . '/TestDiscoverableWidget.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Marko\Layout\Tests\Unit\Fixtures;
        use Marko\Layout\Attributes\Component;
        #[Component(template: 'widget.phtml', handle: 'test_handle')]
        class TestDiscoverableWidget {}
        PHP);

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $moduleRepository = makeModuleRepository([$module]);

    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: makeInnerCollector(),
    );

    $collection = $collector->collect([], 'test_handle');

    expect($collection)->toBeInstanceOf(ComponentCollection::class)
        ->and($collection->count())->toBeGreaterThanOrEqual(1);

    // Cleanup
    unlink($srcDir . '/TestDiscoverableWidget.php');
    rmdir($srcDir);
    rmdir($tmpDir);
});

it('skips classes without Component attribute', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-test-' . bin2hex(random_bytes(8));
    $srcDir = $tmpDir . '/src';
    mkdir($srcDir, 0755, true);

    file_put_contents($srcDir . '/PlainClass.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Marko\Layout\Tests\Unit\Fixtures;
        class PlainClass {}
        PHP);

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $moduleRepository = makeModuleRepository([$module]);

    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: makeInnerCollector(),
    );

    $collection = $collector->collect([], 'test_handle');

    expect($collection->count())->toBe(0);

    // Cleanup
    unlink($srcDir . '/PlainClass.php');
    rmdir($srcDir);
    rmdir($tmpDir);
});

it('skips files that fail to load gracefully', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-test-' . bin2hex(random_bytes(8));
    $srcDir = $tmpDir . '/src';
    mkdir($srcDir, 0755, true);

    // File depends on a missing Marko package — ClassFileParser::loadClass returns false
    file_put_contents($srcDir . '/MissingDepClass.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Marko\Layout\Tests\Unit\Fixtures;
        use Marko\NonExistentPackage\SomeDependency;
        use Marko\Layout\Attributes\Component;
        #[Component(template: 'widget.phtml', handle: 'test_handle')]
        class MissingDepClass extends SomeDependency {}
        PHP);

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $moduleRepository = makeModuleRepository([$module]);

    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: makeInnerCollector(),
    );

    // Should not throw, should return empty collection
    $collection = $collector->collect([], 'test_handle');

    expect($collection->count())->toBe(0);

    // Cleanup
    unlink($srcDir . '/MissingDepClass.php');
    rmdir($srcDir);
    rmdir($tmpDir);
});

it('merges explicitly passed class names with discovered classes', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-test-' . bin2hex(random_bytes(8));
    $srcDir = $tmpDir . '/src';
    mkdir($srcDir, 0755, true);

    file_put_contents($srcDir . '/DiscoveredMergeWidget.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Marko\Layout\Tests\Unit\Fixtures;
        use Marko\Layout\Attributes\Component;
        #[Component(template: 'merge-discovered.phtml', handle: 'merge_handle')]
        class DiscoveredMergeWidget {}
        PHP);

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $moduleRepository = makeModuleRepository([$module]);

    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: makeInnerCollector(),
    );

    // DiscoverableComponent handles 'test_handle' (explicitly passed, won't match 'merge_handle')
    // DiscoveredMergeWidget handles 'merge_handle' (discovered from src/)
    // Both are merged; only DiscoveredMergeWidget matches
    $collection = $collector->collect([DiscoverableComponent::class], 'merge_handle');

    expect($collection->count())->toBe(1);

    // Also verify that passing a handle matching DiscoverableComponent + discovering DiscoveredMergeWidget
    // gives us both when using 'default' (which DiscoveredMergeWidget doesn't handle)
    $collectionForTestHandle = $collector->collect([DiscoverableComponent::class], 'test_handle');

    expect($collectionForTestHandle->count())->toBe(1);

    // Cleanup
    unlink($srcDir . '/DiscoveredMergeWidget.php');
    rmdir($srcDir);
    rmdir($tmpDir);
});

it('delegates to inner ComponentCollector for handle matching', function (): void {
    // Empty module list — no discovery
    $moduleRepository = makeModuleRepository([]);

    $innerCollector = makeInnerCollector();
    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: $innerCollector,
    );

    // DiscoverableComponent has handle 'test_handle'; passing it explicitly
    $collection = $collector->collect([DiscoverableComponent::class], 'test_handle');

    expect($collection->count())->toBe(1)
        ->and($collection->get(DiscoverableComponent::class))->toBeInstanceOf(ComponentDefinition::class);

    // Should NOT match a different handle
    $collectionMismatch = $collector->collect([DiscoverableComponent::class], 'other_handle');

    expect($collectionMismatch->count())->toBe(0);
});

it('delegates discoverFromClass to inner collector', function (): void {
    $moduleRepository = makeModuleRepository([]);

    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: makeInnerCollector(),
    );

    $definition = $collector->discoverFromClass(DiscoverableComponent::class);

    expect($definition)->toBeInstanceOf(ComponentDefinition::class)
        ->and($definition->className)->toBe(DiscoverableComponent::class)
        ->and($definition->template)->toBe('discoverable/component.phtml');

    // Non-component class should return null
    $nullResult = $collector->discoverFromClass(NonComponentClass::class);

    expect($nullResult)->toBeNull();
});

it('handles modules without src directories gracefully', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    // No src/ directory created

    $module = new ModuleManifest(name: 'test/no-src', version: '1.0.0', path: $tmpDir);
    $moduleRepository = makeModuleRepository([$module]);

    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: makeInnerCollector(),
    );

    // Should not throw, should return empty collection
    $collection = $collector->collect([], 'any_handle');

    expect($collection->count())->toBe(0);

    // Cleanup
    rmdir($tmpDir);
});

it('scans all modules from ModuleRepositoryInterface', function (): void {
    $tmpDir1 = sys_get_temp_dir() . '/marko-layout-test-' . bin2hex(random_bytes(8));
    $tmpDir2 = sys_get_temp_dir() . '/marko-layout-test-' . bin2hex(random_bytes(8));
    $srcDir1 = $tmpDir1 . '/src';
    $srcDir2 = $tmpDir2 . '/src';
    mkdir($srcDir1, 0755, true);
    mkdir($srcDir2, 0755, true);

    file_put_contents($srcDir1 . '/ModuleOneWidget.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Marko\Layout\Tests\Unit\Fixtures;
        use Marko\Layout\Attributes\Component;
        #[Component(template: 'module-one.phtml', handle: 'multi_module_handle')]
        class ModuleOneWidget {}
        PHP);

    file_put_contents($srcDir2 . '/ModuleTwoWidget.php', <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Marko\Layout\Tests\Unit\Fixtures;
        use Marko\Layout\Attributes\Component;
        #[Component(template: 'module-two.phtml', handle: 'multi_module_handle')]
        class ModuleTwoWidget {}
        PHP);

    $modules = [
        new ModuleManifest(name: 'test/module-one', version: '1.0.0', path: $tmpDir1),
        new ModuleManifest(name: 'test/module-two', version: '1.0.0', path: $tmpDir2),
    ];
    $moduleRepository = makeModuleRepository($modules);

    $collector = new DiscoveringComponentCollector(
        moduleRepository: $moduleRepository,
        classFileParser: new ClassFileParser(),
        componentCollector: makeInnerCollector(),
    );

    $collection = $collector->collect([], 'multi_module_handle');

    expect($collection->count())->toBe(2);

    // Cleanup
    unlink($srcDir1 . '/ModuleOneWidget.php');
    rmdir($srcDir1);
    rmdir($tmpDir1);
    unlink($srcDir2 . '/ModuleTwoWidget.php');
    rmdir($srcDir2);
    rmdir($tmpDir2);
});
