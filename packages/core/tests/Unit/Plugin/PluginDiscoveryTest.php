<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginDiscovery;

// Helper function for recursive directory cleanup
function cleanupPluginTestDirectory(
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
            cleanupPluginTestDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Helper to create a test plugin class file
function createPluginClass(
    string $path,
    string $className,
    string $namespace,
    string $targetClass,
    array $beforeMethods = [],
    array $afterMethods = [],
): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $useStatements = "use Marko\\Core\\Attributes\\Plugin;\n";
    if (!empty($beforeMethods)) {
        $useStatements .= "use Marko\\Core\\Attributes\\Before;\n";
    }
    if (!empty($afterMethods)) {
        $useStatements .= "use Marko\\Core\\Attributes\\After;\n";
    }

    $methods = '';
    foreach ($beforeMethods as $method => $sortOrder) {
        $sortOrderParam = $sortOrder !== 0 ? "sortOrder: $sortOrder" : '';
        $methods .= "\n    #[Before($sortOrderParam)]\n    public function $method(): void {}\n";
    }
    foreach ($afterMethods as $method => $sortOrder) {
        $sortOrderParam = $sortOrder !== 0 ? "sortOrder: $sortOrder" : '';
        $methods .= "\n    #[After($sortOrderParam)]\n    public function $method(): void {}\n";
    }

    $content = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

$useStatements
#[Plugin(target: $targetClass::class)]
class $className
{
$methods}

PHP;

    file_put_contents($path, $content);
}

it('discovers plugin classes in module src directories', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-plugin-test-' . uniqid();
    mkdir($tempDir . '/src/Plugin', 0755, true);

    // Create a plugin class file
    createPluginClass(
        path: $tempDir . '/src/Plugin/UserServicePlugin.php',
        className: 'UserServicePlugin',
        namespace: 'Acme\Blog\Plugin',
        targetClass: 'Acme\Blog\Services\UserService',
        beforeMethods: ['getUser' => 0],
    );

    // Create composer.json for autoloading
    file_put_contents(
        $tempDir . '/composer.json',
        json_encode([
            'name' => 'acme/blog',
            'autoload' => [
                'psr-4' => ['Acme\\Blog\\' => 'src/'],
            ],
        ]),
    );

    $manifest = new ModuleManifest(
        name: 'acme/blog',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new PluginDiscovery();
    $pluginFiles = $discovery->discoverInModule($manifest);

    expect($pluginFiles)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($pluginFiles[0])->toEndWith('UserServicePlugin.php');

    cleanupPluginTestDirectory($tempDir);
});

it('discovers plugin classes in module src directories with new naming', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-plugin-test-' . uniqid();
    mkdir($tempDir . '/src/Plugin', 0755, true);

    // Create a plugin class file using new unprefixed method naming with #[Before] attribute
    createPluginClass(
        path: $tempDir . '/src/Plugin/ProductServicePlugin.php',
        className: 'ProductServicePlugin',
        namespace: 'Acme\Shop\Plugin',
        targetClass: 'Acme\Shop\Services\ProductService',
        beforeMethods: ['getProduct' => 0],
    );

    file_put_contents(
        $tempDir . '/composer.json',
        json_encode([
            'name' => 'acme/shop',
            'autoload' => [
                'psr-4' => ['Acme\\Shop\\' => 'src/'],
            ],
        ]),
    );

    $manifest = new ModuleManifest(
        name: 'acme/shop',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new PluginDiscovery();
    $pluginFiles = $discovery->discoverInModule($manifest);

    expect($pluginFiles)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($pluginFiles[0])->toEndWith('ProductServicePlugin.php');

    cleanupPluginTestDirectory($tempDir);
});

// Define test fixtures for reflection-based tests
class TargetService
{
    public function doSomething(): string
    {
        return 'result';
    }
}

#[Plugin(target: TargetService::class)]
class TargetServicePlugin
{
    /** @noinspection PhpUnused - Discovered via reflection */
    #[Before(sortOrder: 10)]
    public function doSomething(): void {}

    /** @noinspection PhpUnused - Discovered via reflection */
    #[After(sortOrder: 20)]
    public function doSomethingAfter(): void {}
}

it('extracts target class from Plugin attribute', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition)
        ->toBeInstanceOf(PluginDefinition::class)
        ->and($definition->targetClass)->toBe(TargetService::class);
});

it('extracts target-method-keyed before methods from plugin class', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->beforeMethods)
        ->toBeArray()
        ->toHaveKey('doSomething')
        ->and($definition->beforeMethods['doSomething'])->toHaveKey('pluginMethod')
        ->and($definition->beforeMethods['doSomething'])->toHaveKey('sortOrder')
        ->and($definition->beforeMethods['doSomething']['pluginMethod'])->toBe('doSomething')
        ->and($definition->beforeMethods['doSomething']['sortOrder'])->toBe(10);
});

it('extracts before methods with their sort orders', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->beforeMethods)
        ->toBeArray()
        ->toHaveKey('doSomething')
        ->and($definition->beforeMethods['doSomething']['sortOrder'])->toBe(10);
});

it('extracts target-method-keyed after methods from plugin class', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->afterMethods)
        ->toBeArray()
        ->toHaveKey('doSomethingAfter')
        ->and($definition->afterMethods['doSomethingAfter'])->toHaveKey('pluginMethod')
        ->and($definition->afterMethods['doSomethingAfter'])->toHaveKey('sortOrder')
        ->and($definition->afterMethods['doSomethingAfter']['pluginMethod'])->toBe('doSomethingAfter')
        ->and($definition->afterMethods['doSomethingAfter']['sortOrder'])->toBe(20);
});

it('extracts after methods with their sort orders', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->afterMethods)
        ->toBeArray()
        ->toHaveKey('doSomethingAfter')
        ->and($definition->afterMethods['doSomethingAfter']['sortOrder'])->toBe(20);
});

// Test fixture for class without Plugin attribute
class NotAPlugin
{
    public function doSomething(): void {}
}

it('throws PluginException when Plugin attribute missing target', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->parsePluginClass(NotAPlugin::class))
        ->toThrow(PluginException::class);
});

// Test fixture for class with Before/After attributes but no Plugin attribute
class InvalidPluginWithBeforeAfter
{
    /** @noinspection PhpUnused - Discovered via reflection */
    #[Before]
    public function beforeSomething(): void {}

    /** @noinspection PhpUnused - Discovered via reflection */
    #[After]
    public function afterSomething(): void {}
}

it('throws PluginException when before/after method on non-plugin class', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->validatePluginMethods(InvalidPluginWithBeforeAfter::class))
        ->toThrow(PluginException::class);
});

it('validates orphaned plugin methods still throws exception', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->validatePluginMethods(InvalidPluginWithBeforeAfter::class))
        ->toThrow(PluginException::class);
});

// Fixtures for new target-method-keyed structure tests

class SimpleTargetService
{
    public function save(): void {}
}

#[Plugin(target: SimpleTargetService::class)]
class SimpleTargetPlugin
{
    /** @noinspection PhpUnused */
    #[Before(sortOrder: 5)]
    public function save(): void {}

    /** @noinspection PhpUnused */
    #[After(sortOrder: 10)]
    public function save2(): void {}
}

it('resolves target method from plugin method name when no method param', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(SimpleTargetPlugin::class);

    expect($definition->beforeMethods)
        ->toHaveKey('save')
        ->and($definition->beforeMethods['save']['pluginMethod'])->toBe('save')
        ->and($definition->beforeMethods['save']['sortOrder'])->toBe(5);
});

class ExplicitMethodTargetService
{
    public function save(): void {}

    public function delete(): void {}
}

#[Plugin(target: ExplicitMethodTargetService::class)]
class ExplicitBeforePlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'save', sortOrder: 3)]
    public function validateInput(): void {}
}

#[Plugin(target: ExplicitMethodTargetService::class)]
class ExplicitAfterPlugin
{
    /** @noinspection PhpUnused */
    #[After(method: 'delete', sortOrder: 7)]
    public function auditDelete(): void {}
}

it('resolves target method from Before attribute method param', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(ExplicitBeforePlugin::class);

    expect($definition->beforeMethods)
        ->toHaveKey('save')
        ->and($definition->beforeMethods['save']['pluginMethod'])->toBe('validateInput')
        ->and($definition->beforeMethods['save']['sortOrder'])->toBe(3);
});

it('resolves target method from After attribute method param', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(ExplicitAfterPlugin::class);

    expect($definition->afterMethods)
        ->toHaveKey('delete')
        ->and($definition->afterMethods['delete']['pluginMethod'])->toBe('auditDelete')
        ->and($definition->afterMethods['delete']['sortOrder'])->toBe(7);
});

class MappingTargetService
{
    public function process(): void {}
}

#[Plugin(target: MappingTargetService::class)]
class MappingPlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'process', sortOrder: 1)]
    public function validateProcess(): void {}
}

it('builds PluginDefinition with correct target-to-plugin method mapping', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(MappingPlugin::class);

    expect($definition->pluginClass)->toBe(MappingPlugin::class)
        ->and($definition->targetClass)->toBe(MappingTargetService::class)
        ->and($definition->beforeMethods)->toHaveKey('process')
        ->and($definition->beforeMethods['process']['pluginMethod'])->toBe('validateProcess')
        ->and($definition->beforeMethods['process']['sortOrder'])->toBe(1);
});

class MixedTargetService
{
    public function run(): void {}

    public function stop(): void {}
}

#[Plugin(target: MixedTargetService::class)]
class MixedPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function run(): void {}

    /** @noinspection PhpUnused */
    #[After(method: 'stop', sortOrder: 15)]
    public function logStop(): void {}
}

it('handles mixed standard and explicit method params in same plugin', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(MixedPlugin::class);

    expect($definition->beforeMethods)
        ->toHaveKey('run')
        ->and($definition->beforeMethods['run']['pluginMethod'])->toBe('run')
        ->and($definition->beforeMethods['run']['sortOrder'])->toBe(0)
        ->and($definition->afterMethods)->toHaveKey('stop')
        ->and($definition->afterMethods['stop']['pluginMethod'])->toBe('logStop')
        ->and($definition->afterMethods['stop']['sortOrder'])->toBe(15);
});

// Fixtures for duplicate hook detection tests

class DuplicateBeforeTargetService
{
    public function save(): void {}
}

#[Plugin(target: DuplicateBeforeTargetService::class)]
class DuplicateBeforePlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'save')]
    public function validateInput(): void {}

    /** @noinspection PhpUnused */
    #[Before(method: 'save')]
    public function sanitizeInput(): void {}
}

it('throws PluginException when two before methods in same class target the same method', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->parsePluginClass(DuplicateBeforePlugin::class))
        ->toThrow(PluginException::class);
});

class DuplicateAfterTargetService
{
    public function save(): void {}
}

#[Plugin(target: DuplicateAfterTargetService::class)]
class DuplicateAfterPlugin
{
    /** @noinspection PhpUnused */
    #[After(method: 'save')]
    public function logSave(): void {}

    /** @noinspection PhpUnused */
    #[After(method: 'save')]
    public function auditSave(): void {}
}

it('throws PluginException when two after methods in same class target the same method', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->parsePluginClass(DuplicateAfterPlugin::class))
        ->toThrow(PluginException::class);
});

class SameMethodBeforeAfterTargetService
{
    public function save(): void {}
}

#[Plugin(target: SameMethodBeforeAfterTargetService::class)]
class SameMethodBeforeAfterPlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'save')]
    public function beforeSave(): void {}

    /** @noinspection PhpUnused */
    #[After(method: 'save')]
    public function afterSave(): void {}
}

it('allows before and after targeting the same method via method param in same class', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(SameMethodBeforeAfterPlugin::class);

    expect($definition->beforeMethods)->toHaveKey('save')
        ->and($definition->afterMethods)->toHaveKey('save');
});

it('provides helpful error message with class and method names for intra-class conflict', function (): void {
    $discovery = new PluginDiscovery();

    try {
        $discovery->parsePluginClass(DuplicateBeforePlugin::class);
        expect(false)->toBeTrue('Expected PluginException to be thrown');
    } catch (PluginException $e) {
        expect($e->getMessage())->toContain('DuplicateBeforePlugin')
            ->and($e->getMessage())->toContain('before')
            ->and($e->getMessage())->toContain('save')
            ->and($e->getContext())->toContain('DuplicateBeforePlugin')
            ->and($e->getSuggestion())->not->toBeEmpty();
    }
});
