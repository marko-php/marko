<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\ModuleException;

it('creates Application class as main entry point', function (): void {
    $app = new Application();

    expect($app)->toBeInstanceOf(Application::class);
});

it('accepts base paths for vendor, modules, and app directories', function (): void {
    $app = new Application(
        vendorPath: '/path/to/vendor',
        modulesPath: '/path/to/modules',
        appPath: '/path/to/app',
    );

    expect($app->vendorPath)->toBe('/path/to/vendor')
        ->and($app->modulesPath)->toBe('/path/to/modules')
        ->and($app->appPath)->toBe('/path/to/app');
});

// Helper function for recursive directory cleanup
function appTestCleanupDirectory(
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
            appTestCleanupDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Helper to create a test module with composer.json and optional module.php
function appTestCreateModule(
    string $path,
    string $name,
    string $version = '1.0.0',
    array $require = [],
    ?array $modulePhp = null,
): void {
    mkdir($path, 0755, true);

    // Create composer.json (required)
    $composerData = [
        'name' => $name,
        'version' => $version,
    ];
    if (!empty($require)) {
        $composerData['require'] = $require;
    }
    file_put_contents($path . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create module.php (optional)
    if ($modulePhp !== null) {
        $modulePhpContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($modulePhp, true) . ";\n";
        file_put_contents($path . '/module.php', $modulePhpContent);
    }
}

it('scans all three directories for modules during boot', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';
    $modulesDir = $baseDir . '/modules';
    $appDir = $baseDir . '/app';

    // Create modules in each directory
    appTestCreateModule($vendorDir . '/marko/core', 'marko/core');
    appTestCreateModule($modulesDir . '/custom/checkout', 'custom/checkout', '1.0.0', ['marko/core' => '^1.0']);
    appTestCreateModule($appDir . '/blog', 'app/blog', '1.0.0', ['marko/core' => '^1.0']);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: $modulesDir,
        appPath: $appDir,
    );

    $app->boot();

    $modules = $app->modules;
    $moduleNames = array_map(fn ($m) => $m->name, $modules);

    expect($moduleNames)
        ->toContain('marko/core')
        ->toContain('custom/checkout')
        ->toContain('app/blog');

    appTestCleanupDirectory($baseDir);
});

it('validates module dependencies exist', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    // Create a module that requires a non-existent dependency
    appTestCreateModule(
        $vendorDir . '/acme/blog',
        'acme/blog',
        '1.0.0',
        ['marko/missing-module' => '^1.0'],
    );

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    expect(fn () => $app->boot())->toThrow(ModuleException::class);

    appTestCleanupDirectory($baseDir);
});

it('detects and reports circular dependencies', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    // Create modules with circular dependency: A depends on B, B depends on A
    appTestCreateModule(
        $vendorDir . '/acme/module-a',
        'acme/module-a',
        '1.0.0',
        ['acme/module-b' => '^1.0'],
    );
    appTestCreateModule(
        $vendorDir . '/acme/module-b',
        'acme/module-b',
        '1.0.0',
        ['acme/module-a' => '^1.0'],
    );

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    expect(fn () => $app->boot())->toThrow(CircularDependencyException::class);

    appTestCleanupDirectory($baseDir);
});

it('sorts modules in correct load order', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    // Create modules where C depends on B depends on A
    appTestCreateModule($vendorDir . '/acme/module-c', 'acme/module-c', '1.0.0', ['acme/module-b' => '^1.0']);
    appTestCreateModule($vendorDir . '/acme/module-a', 'acme/module-a');
    appTestCreateModule($vendorDir . '/acme/module-b', 'acme/module-b', '1.0.0', ['acme/module-a' => '^1.0']);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $modules = $app->modules;
    $moduleNames = array_map(fn ($m) => $m->name, $modules);

    // A must come before B, B must come before C
    $aIndex = array_search('acme/module-a', $moduleNames);
    $bIndex = array_search('acme/module-b', $moduleNames);
    $cIndex = array_search('acme/module-c', $moduleNames);

    expect($aIndex)->toBeLessThan($bIndex)
        ->and($bIndex)->toBeLessThan($cIndex);

    appTestCleanupDirectory($baseDir);
});

it('registers bindings from all modules', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    // Create a module with bindings
    appTestCreateModule(
        $vendorDir . '/acme/core',
        'acme/core',
        '1.0.0',
        [],
        [
            'bindings' => [
                'Acme\Core\Contracts\LoggerInterface' => 'Acme\Core\Logger\FileLogger',
            ],
        ],
    );

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $container = $app->container;

    // The container should have the binding registered
    expect($container->has('Acme\Core\Contracts\LoggerInterface'))->toBeTrue();

    appTestCleanupDirectory($baseDir);
});

it('discovers and registers preferences', function (): void {
    // Use unique class names to avoid conflicts between test runs
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with a preference class
    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');

    // Create the src directory with a preference class
    mkdir($modulePath . '/src', 0755, true);

    // Create original class first (no Preference attribute)
    $originalCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePrefs$uniqueId;

class OriginalClass$uniqueId
{
    public function getValue(): string
    {
        return 'original';
    }
}
PHP;
    file_put_contents($modulePath . '/src/OriginalClass.php', $originalCode);

    // Create preference class that replaces the original
    $preferenceCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePrefs$uniqueId;

use Marko\\Core\\Attributes\\Preference;

#[Preference(replaces: OriginalClass$uniqueId::class)]
class ReplacementClass$uniqueId extends OriginalClass$uniqueId
{
    public function getValue(): string
    {
        return 'replacement';
    }
}
PHP;
    file_put_contents($modulePath . '/src/ReplacementClass.php', $preferenceCode);

    // Pre-load the original class before boot
    require_once $modulePath . '/src/OriginalClass.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    // The container should resolve the original class to the replacement
    $container = $app->container;

    $originalClass = "AcmePrefs$uniqueId\\OriginalClass$uniqueId";
    $replacementClass = "AcmePrefs$uniqueId\\ReplacementClass$uniqueId";
    $instance = $container->get($originalClass);

    expect($instance)->toBeInstanceOf($replacementClass);

    appTestCleanupDirectory($baseDir);
});

it('discovers and registers plugins', function (): void {
    // Use unique class names to avoid conflicts between test runs
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with a plugin class
    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');

    // Create the src directory with classes
    mkdir($modulePath . '/src', 0755, true);

    // Create target class
    $targetCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePlugin$uniqueId;

class TargetClass$uniqueId
{
    public function doSomething(): string
    {
        return 'original';
    }
}
PHP;
    file_put_contents($modulePath . '/src/TargetClass.php', $targetCode);

    // Create plugin class
    $pluginCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePlugin$uniqueId;

use Marko\\Core\\Attributes\\Plugin;
use Marko\\Core\\Attributes\\Before;

#[Plugin(target: TargetClass$uniqueId::class)]
class TargetPlugin$uniqueId
{
    #[Before]
    public function beforeDoSomething(): void
    {
        // Plugin logic
    }
}
PHP;
    file_put_contents($modulePath . '/src/TargetPlugin.php', $pluginCode);

    // Pre-load the target class
    require_once $modulePath . '/src/TargetClass.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    // The plugin registry should have the plugin registered
    $pluginRegistry = $app->pluginRegistry;
    $targetClass = "AcmePlugin$uniqueId\\TargetClass$uniqueId";

    expect($pluginRegistry->hasPluginsFor($targetClass))->toBeTrue();

    appTestCleanupDirectory($baseDir);
});

it('discovers and registers observers', function (): void {
    // Use unique class names to avoid conflicts between test runs
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with an observer class
    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');

    // Create the src directory with classes
    mkdir($modulePath . '/src', 0755, true);

    // Create event class
    $eventCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeObserver$uniqueId;

use Marko\\Core\\Event\\Event;

class UserCreatedEvent$uniqueId extends Event
{
}
PHP;
    file_put_contents($modulePath . '/src/UserCreatedEvent.php', $eventCode);

    // Create observer class
    $observerCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeObserver$uniqueId;

use Marko\\Core\\Attributes\\Observer;

#[Observer(event: UserCreatedEvent$uniqueId::class)]
class UserCreatedObserver$uniqueId
{
    public function handle(
        UserCreatedEvent$uniqueId \$event,
    ): void {
        // Observer logic
    }
}
PHP;
    file_put_contents($modulePath . '/src/UserCreatedObserver.php', $observerCode);

    // Pre-load the event class
    require_once $modulePath . '/src/UserCreatedEvent.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    // The observer registry should have observers for the event
    $observerRegistry = $app->observerRegistry;
    $eventClass = "AcmeObserver$uniqueId\\UserCreatedEvent$uniqueId";
    $observers = $observerRegistry->getObserversFor($eventClass);

    expect($observers)->toHaveCount(1);

    appTestCleanupDirectory($baseDir);
});

it('provides access to configured container', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $container = $app->container;

    expect($container)->toBeInstanceOf(ContainerInterface::class);

    appTestCleanupDirectory($baseDir);
});

it('provides access to event dispatcher', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $dispatcher = $app->eventDispatcher;

    expect($dispatcher)->toBeInstanceOf(EventDispatcherInterface::class);

    appTestCleanupDirectory($baseDir);
});

it('bootstrap.php creates and boots Application instance', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';
    $modulesDir = $baseDir . '/modules';
    $appDir = $baseDir . '/app';

    // Create the directory structure
    mkdir($vendorDir, 0755, true);
    mkdir($modulesDir, 0755, true);
    mkdir($appDir, 0755, true);

    // Create a test module
    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    // Get the path to the actual bootstrap.php
    $bootstrapPath = dirname(__DIR__, 2) . '/bootstrap.php';

    // Verify bootstrap.php exists
    expect(file_exists($bootstrapPath))->toBeTrue();

    // Include bootstrap and pass the paths
    $app = (require $bootstrapPath)(
        vendorPath: $vendorDir,
        modulesPath: $modulesDir,
        appPath: $appDir,
    );

    expect($app)->toBeInstanceOf(Application::class)
        ->and($app->modules)->toHaveCount(1);

    appTestCleanupDirectory($baseDir);
});

it('parses all discovered module manifests', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule(
        $vendorDir . '/marko/core',
        'marko/core',
        '2.0.0',
        [],
        [
            'bindings' => ['SomeInterface' => 'SomeClass'],
        ],
    );

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $modules = $app->modules;

    expect($modules)->toHaveCount(1)
        ->and($modules[0]->name)->toBe('marko/core')
        ->and($modules[0]->version)->toBe('2.0.0')
        ->and($modules[0]->bindings)->toBe(['SomeInterface' => 'SomeClass']);

    appTestCleanupDirectory($baseDir);
});

it('registers PSR-4 autoloaders for modules source during boot', function (): void {
    // Use unique namespace to avoid conflicts between test runs
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $modulesDir = $baseDir . '/modules';

    // Create module with autoload configuration
    $modulePath = $modulesDir . '/custom-blog';
    // Note: we use a 'lib/' directory (not 'src/') to avoid the file being
    // loaded by preference/plugin discovery which scans only 'src/'
    mkdir($modulePath . '/lib', 0755, true);

    // Create composer.json with autoload.psr-4
    $composerData = [
        'name' => 'custom/blog',
        'version' => '1.0.0',
        'autoload' => [
            'psr-4' => [
                "CustomBlog$uniqueId\\" => 'lib/',
            ],
        ],
    ];
    file_put_contents($modulePath . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create a class file in the module's lib/ directory
    $classContent = <<<PHP
<?php

declare(strict_types=1);

namespace CustomBlog$uniqueId;

class BlogService
{
    public function getName(): string
    {
        return 'BlogService';
    }
}
PHP;
    file_put_contents($modulePath . '/lib/BlogService.php', $classContent);

    // Verify class doesn't exist BEFORE boot
    $className = "CustomBlog$uniqueId\\BlogService";
    expect(class_exists($className, false))->toBeFalse()
        ->and(class_exists($className))->toBeFalse('Class should NOT be autoloadable before boot');

    $app = new Application(
        vendorPath: '',
        modulesPath: $modulesDir,
        appPath: '',
    );

    $app->boot();

    // The class should now be autoloadable via the PSR-4 autoloader
    expect(class_exists($className))->toBeTrue('Class should be autoloadable after boot');

    // Instantiate the class to confirm it truly works
    $instance = new $className();
    expect($instance->getName())->toBe('BlogService');

    appTestCleanupDirectory($baseDir);
});

it('skips autoloader registration for vendor modules', function (): void {
    // Count autoloaders before and after boot to verify vendor modules don't add autoloaders
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a vendor module with autoload configuration
    $modulePath = $vendorDir . '/acme/core';
    mkdir($modulePath . '/lib', 0755, true);

    // Create composer.json with autoload.psr-4
    $composerData = [
        'name' => 'acme/core',
        'version' => '1.0.0',
        'autoload' => [
            'psr-4' => [
                "AcmeCore$uniqueId\\" => 'lib/',
            ],
        ],
    ];
    file_put_contents($modulePath . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create a class file in the vendor module
    $classContent = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeCore$uniqueId;

class CoreService
{
    public function getName(): string
    {
        return 'CoreService';
    }
}
PHP;
    file_put_contents($modulePath . '/lib/CoreService.php', $classContent);

    $autoloaderCountBefore = count(spl_autoload_functions());

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $autoloaderCountAfter = count(spl_autoload_functions());

    // Should not have registered any new autoloaders for vendor modules
    expect($autoloaderCountAfter)->toBe($autoloaderCountBefore);

    // The class should NOT be autoloadable (Composer didn't know about this test module)
    $className = "AcmeCore$uniqueId\\CoreService";
    expect(class_exists($className))->toBeFalse(
        'Vendor module class should not be autoloadable without Composer',
    );

    appTestCleanupDirectory($baseDir);
});

it('resolves class from app module without explicit require in root composer.json', function (): void {
    // This simulates app/blog working without being in demo/composer.json require
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $appDir = $baseDir . '/app';

    // Create an app module (like demo/app/blog)
    $modulePath = $appDir . '/blog';
    mkdir($modulePath . '/src', 0755, true);

    // Create composer.json with PSR-4 autoload
    $composerData = [
        'name' => 'app/blog',
        'version' => '1.0.0',
        'autoload' => [
            'psr-4' => [
                "App\\Blog$uniqueId\\" => 'src/',
            ],
        ],
    ];
    file_put_contents($modulePath . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create the module's controller class
    $classContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Blog$uniqueId\\Controller;

class BlogController
{
    public function index(): string
    {
        return 'Blog index';
    }
}
PHP;
    mkdir($modulePath . '/src/Controller', 0755, true);
    file_put_contents($modulePath . '/src/Controller/BlogController.php', $classContent);

    // Also create a model class to test nested namespaces
    $modelContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Blog$uniqueId\\Model;

class Post
{
    public function __construct(
        public string \$title = 'Default Title',
    ) {}
}
PHP;
    mkdir($modulePath . '/src/Model', 0755, true);
    file_put_contents($modulePath . '/src/Model/Post.php', $modelContent);

    // Verify classes don't exist before boot
    $controllerClass = "App\\Blog$uniqueId\\Controller\\BlogController";
    $modelClass = "App\\Blog$uniqueId\\Model\\Post";

    expect(class_exists($controllerClass))->toBeFalse()
        ->and(class_exists($modelClass))->toBeFalse();

    $app = new Application(
        vendorPath: '',
        modulesPath: '',
        appPath: $appDir,
    );

    $app->boot();

    // Classes should now be autoloadable
    expect(class_exists($controllerClass))->toBeTrue('Controller should be autoloadable')
        ->and(class_exists($modelClass))->toBeTrue('Model should be autoloadable');

    // Actually instantiate and use the classes
    $controller = new $controllerClass();
    $post = new $modelClass('Test Post');

    expect($controller->index())->toBe('Blog index')
        ->and($post->title)->toBe('Test Post');

    appTestCleanupDirectory($baseDir);
});

it('resolves class from modules directory without explicit require in root composer.json', function (): void {
    // This simulates modules/custom-checkout working without being in root composer.json
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $modulesDir = $baseDir . '/modules';

    // Create a modules directory module
    $modulePath = $modulesDir . '/custom-checkout';
    mkdir($modulePath . '/src', 0755, true);

    // Create composer.json with PSR-4 autoload
    $composerData = [
        'name' => 'custom/checkout',
        'version' => '1.0.0',
        'autoload' => [
            'psr-4' => [
                "Custom\\Checkout$uniqueId\\" => 'src/',
            ],
        ],
    ];
    file_put_contents($modulePath . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create the module's service class
    $classContent = <<<PHP
<?php

declare(strict_types=1);

namespace Custom\\Checkout$uniqueId\\Service;

class CartService
{
    public function getTotal(): float
    {
        return 99.99;
    }
}
PHP;
    mkdir($modulePath . '/src/Service', 0755, true);
    file_put_contents($modulePath . '/src/Service/CartService.php', $classContent);

    // Verify class doesn't exist before boot
    $serviceClass = "Custom\\Checkout$uniqueId\\Service\\CartService";
    expect(class_exists($serviceClass))->toBeFalse();

    $app = new Application(
        vendorPath: '',
        modulesPath: $modulesDir,
        appPath: '',
    );

    $app->boot();

    // Class should now be autoloadable
    expect(class_exists($serviceClass))->toBeTrue('Service should be autoloadable');

    // Actually instantiate and use the class
    $service = new $serviceClass();
    expect($service->getTotal())->toBe(99.99);

    appTestCleanupDirectory($baseDir);
});
