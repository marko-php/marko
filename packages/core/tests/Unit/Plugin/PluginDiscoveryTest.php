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
function cleanupPluginTestDirectory(string $dir): void
{
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
        beforeMethods: ['beforeGetUser' => 0],
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
    #[Before(sortOrder: 10)]
    public function beforeDoSomething(): void {}

    #[After(sortOrder: 20)]
    public function afterDoSomething(): void {}
}

it('extracts target class from Plugin attribute', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition)
        ->toBeInstanceOf(PluginDefinition::class)
        ->and($definition->targetClass)->toBe(TargetService::class);
});

it('extracts before methods with their sort orders', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->beforeMethods)
        ->toBeArray()
        ->toHaveKey('beforeDoSomething')
        ->and($definition->beforeMethods['beforeDoSomething'])->toBe(10);
});

it('extracts after methods with their sort orders', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->afterMethods)
        ->toBeArray()
        ->toHaveKey('afterDoSomething')
        ->and($definition->afterMethods['afterDoSomething'])->toBe(20);
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
    #[Before]
    public function beforeSomething(): void {}

    #[After]
    public function afterSomething(): void {}
}

it('throws PluginException when before/after method on non-plugin class', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->validatePluginMethods(InvalidPluginWithBeforeAfter::class))
        ->toThrow(PluginException::class);
});
