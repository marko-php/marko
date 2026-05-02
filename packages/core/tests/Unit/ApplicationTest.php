<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

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
    // Add extra.marko.module: true to mark as Marko module
    $composerData['extra'] = [
        'marko' => [
            'module' => true,
        ],
    ];
    file_put_contents($path . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create module.php (optional)
    if ($modulePhp !== null) {
        $modulePhpContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($modulePhp, true) . ";\n";
        file_put_contents($path . '/module.php', $modulePhpContent);
    }
}

it('scans all three directories for modules during boot', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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

    $app->initialize();

    $modules = $app->modules;
    $moduleNames = array_map(fn ($m) => $m->name, $modules);

    expect($moduleNames)
        ->toContain('marko/core')
        ->toContain('custom/checkout')
        ->toContain('app/blog');

    appTestCleanupDirectory($baseDir);
});

it('ignores dependencies that are not discovered Marko modules', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    // Create a module that requires a package not in our modules list
    // This could be a regular Composer package like psr/container
    appTestCreateModule(
        $vendorDir . '/acme/blog',
        'acme/blog',
        '1.0.0',
        ['psr/container' => '^2.0'],
    );

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    // Should boot successfully - non-Marko dependencies are ignored
    $app->initialize();

    expect($app->modules)->toHaveCount(1)
        ->and($app->modules[0]->name)->toBe('acme/blog');

    appTestCleanupDirectory($baseDir);
});

it('detects and reports circular dependencies', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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

    expect(fn () => $app->initialize())->toThrow(CircularDependencyException::class);

    appTestCleanupDirectory($baseDir);
});

it('sorts modules in correct load order', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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

    $app->initialize();

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
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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

    $app->initialize();

    $container = $app->container;

    // The container should have the binding registered
    expect($container->has('Acme\Core\Contracts\LoggerInterface'))->toBeTrue();

    appTestCleanupDirectory($baseDir);
});

it('discovers and registers preferences', function (): void {
    // Use unique class names to avoid conflicts between test runs
    $uniqueId = bin2hex(random_bytes(8));
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

    $app->initialize();

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
    $uniqueId = bin2hex(random_bytes(8));
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

    $app->initialize();

    // The plugin registry should have the plugin registered
    $pluginRegistry = $app->pluginRegistry;
    $targetClass = "AcmePlugin$uniqueId\\TargetClass$uniqueId";

    expect($pluginRegistry->hasPluginsFor($targetClass))->toBeTrue();

    appTestCleanupDirectory($baseDir);
});

it('discovers and registers plugins in ApplicationTest with new naming', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');

    mkdir($modulePath . '/src', 0755, true);

    $targetCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePluginNew$uniqueId;

class TargetClass$uniqueId
{
    public function doSomething(): string
    {
        return 'original';
    }
}
PHP;
    file_put_contents($modulePath . '/src/TargetClass.php', $targetCode);

    $pluginCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePluginNew$uniqueId;

use Marko\\Core\\Attributes\\Plugin;
use Marko\\Core\\Attributes\\Before;

#[Plugin(target: TargetClass$uniqueId::class)]
class TargetPlugin$uniqueId
{
    #[Before]
    public function doSomething(): void
    {
        // Plugin logic using new naming convention
    }
}
PHP;
    file_put_contents($modulePath . '/src/TargetPlugin.php', $pluginCode);

    require_once $modulePath . '/src/TargetClass.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    $pluginRegistry = $app->pluginRegistry;
    $targetClass = "AcmePluginNew$uniqueId\\TargetClass$uniqueId";

    expect($pluginRegistry->hasPluginsFor($targetClass))->toBeTrue();

    appTestCleanupDirectory($baseDir);
});

it('discovers and registers observers', function (): void {
    // Use unique class names to avoid conflicts between test runs
    $uniqueId = bin2hex(random_bytes(8));
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

    $app->initialize();

    // The observer registry should have observers for the event
    $observerRegistry = $app->observerRegistry;
    $eventClass = "AcmeObserver$uniqueId\\UserCreatedEvent$uniqueId";
    $observers = $observerRegistry->getObserversFor($eventClass);

    expect($observers)->toHaveCount(1);

    appTestCleanupDirectory($baseDir);
});

it('provides access to configured container', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    $container = $app->container;

    expect($container)->toBeInstanceOf(ContainerInterface::class);

    appTestCleanupDirectory($baseDir);
});

it('provides access to event dispatcher', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    $dispatcher = $app->eventDispatcher;

    expect($dispatcher)->toBeInstanceOf(EventDispatcherInterface::class);

    appTestCleanupDirectory($baseDir);
});

it('bootstrap.php creates and boots Application instance', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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

    $app->initialize();

    $modules = $app->modules;

    expect($modules)->toHaveCount(1)
        ->and($modules[0]->name)->toBe('marko/core')
        ->and($modules[0]->version)->toBe('2.0.0')
        ->and($modules[0]->bindings)->toBe(['SomeInterface' => 'SomeClass']);

    appTestCleanupDirectory($baseDir);
});

it('registers PSR-4 autoloaders for modules source during boot', function (): void {
    // Use unique namespace to avoid conflicts between test runs
    $uniqueId = bin2hex(random_bytes(8));
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
        'extra' => [
            'marko' => [
                'module' => true,
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

    $app->initialize();

    // The class should now be autoloadable via the PSR-4 autoloader
    expect(class_exists($className))->toBeTrue('Class should be autoloadable after boot');

    // Instantiate the class to confirm it truly works
    $instance = new $className();
    expect($instance->getName())->toBe('BlogService');

    appTestCleanupDirectory($baseDir);
});

it('skips autoloader registration for vendor modules', function (): void {
    // Count autoloaders before and after boot to verify vendor modules don't add autoloaders
    $uniqueId = bin2hex(random_bytes(8));
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
        'extra' => [
            'marko' => [
                'module' => true,
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

    $app->initialize();

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
    $uniqueId = bin2hex(random_bytes(8));
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
        'extra' => [
            'marko' => [
                'module' => true,
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

    $app->initialize();

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
    $uniqueId = bin2hex(random_bytes(8));
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
        'extra' => [
            'marko' => [
                'module' => true,
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

    $app->initialize();

    // Class should now be autoloadable
    expect(class_exists($serviceClass))->toBeTrue('Service should be autoloadable');

    // Actually instantiate and use the class
    $service = new $serviceClass();
    expect($service->getTotal())->toBe(99.99);

    appTestCleanupDirectory($baseDir);
});

it('discovers commands during application boot', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with a command class
    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');

    mkdir($modulePath . '/src', 0755, true);

    // Create command class
    $commandCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeCommand$uniqueId;

use Marko\\Core\\Attributes\\Command;
use Marko\\Core\\Command\\CommandInterface;
use Marko\\Core\\Command\\Input;
use Marko\\Core\\Command\\Output;

#[Command(name: 'test:hello', description: 'A test command')]
class HelloCommand implements CommandInterface
{
    public function execute(
        Input \$input,
        Output \$output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($modulePath . '/src/HelloCommand.php', $commandCode);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    // The command registry should have the command registered
    $commandRegistry = $app->commandRegistry;
    expect($commandRegistry->has('test:hello'))->toBeTrue();

    appTestCleanupDirectory($baseDir);
});

it('exposes commandRegistry property on Application', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    expect($app->commandRegistry)->toBeInstanceOf(CommandRegistry::class);

    appTestCleanupDirectory($baseDir);
});

it('exposes commandRunner property on Application', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    expect($app->commandRunner)->toBeInstanceOf(CommandRunner::class);

    appTestCleanupDirectory($baseDir);
});

it('registers commands from all enabled modules', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';
    $modulesDir = $baseDir . '/modules';
    $appDir = $baseDir . '/app';

    // Create a vendor module with a command
    $vendorModulePath = $vendorDir . '/acme/core';
    appTestCreateModule($vendorModulePath, 'acme/core');
    mkdir($vendorModulePath . '/src', 0755, true);

    $vendorCommandCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeVendorCmd$uniqueId;

use Marko\\Core\\Attributes\\Command;
use Marko\\Core\\Command\\CommandInterface;
use Marko\\Core\\Command\\Input;
use Marko\\Core\\Command\\Output;

#[Command(name: 'vendor:cmd', description: 'Vendor command')]
class VendorCommand implements CommandInterface
{
    public function execute(
        Input \$input,
        Output \$output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($vendorModulePath . '/src/VendorCommand.php', $vendorCommandCode);

    // Create a modules module with a command
    $modulesModulePath = $modulesDir . '/custom/checkout';
    appTestCreateModule($modulesModulePath, 'custom/checkout', '1.0.0', ['acme/core' => '^1.0']);
    mkdir($modulesModulePath . '/src', 0755, true);

    $modulesCommandCode = <<<PHP
<?php

declare(strict_types=1);

namespace CustomModulesCmd$uniqueId;

use Marko\\Core\\Attributes\\Command;
use Marko\\Core\\Command\\CommandInterface;
use Marko\\Core\\Command\\Input;
use Marko\\Core\\Command\\Output;

#[Command(name: 'modules:cmd', description: 'Modules command')]
class ModulesCommand implements CommandInterface
{
    public function execute(
        Input \$input,
        Output \$output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($modulesModulePath . '/src/ModulesCommand.php', $modulesCommandCode);

    // Create an app module with a command
    $appModulePath = $appDir . '/blog';
    appTestCreateModule($appModulePath, 'app/blog', '1.0.0', ['acme/core' => '^1.0']);
    mkdir($appModulePath . '/src', 0755, true);

    $appCommandCode = <<<PHP
<?php

declare(strict_types=1);

namespace AppBlogCmd$uniqueId;

use Marko\\Core\\Attributes\\Command;
use Marko\\Core\\Command\\CommandInterface;
use Marko\\Core\\Command\\Input;
use Marko\\Core\\Command\\Output;

#[Command(name: 'app:cmd', description: 'App command')]
class AppCommand implements CommandInterface
{
    public function execute(
        Input \$input,
        Output \$output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($appModulePath . '/src/AppCommand.php', $appCommandCode);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: $modulesDir,
        appPath: $appDir,
    );

    $app->initialize();

    // All commands from all modules should be registered
    $commandRegistry = $app->commandRegistry;
    expect($commandRegistry->has('vendor:cmd'))->toBeTrue()
        ->and($commandRegistry->has('modules:cmd'))->toBeTrue()
        ->and($commandRegistry->has('app:cmd'))->toBeTrue();

    appTestCleanupDirectory($baseDir);
});

it('skips modules without src directory during command discovery', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    // Create a module without src directory
    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    // Should not throw, and registry should be empty
    $app->initialize();

    expect($app->commandRegistry->all())->toBeEmpty();

    appTestCleanupDirectory($baseDir);
});

it('skips modules without command classes', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with src directory but no command classes
    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');
    mkdir($modulePath . '/src', 0755, true);

    // Create a regular class (not a command)
    $regularClassCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeRegular$uniqueId;

class RegularService
{
    public function doSomething(): void {}
}
PHP;
    file_put_contents($modulePath . '/src/RegularService.php', $regularClassCode);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    expect($app->commandRegistry->all())->toBeEmpty();

    appTestCleanupDirectory($baseDir);
});

it('makes commandRunner available after boot', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with a command
    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');
    mkdir($modulePath . '/src', 0755, true);

    $commandCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeRunnerTest$uniqueId;

use Marko\\Core\\Attributes\\Command;
use Marko\\Core\\Command\\CommandInterface;
use Marko\\Core\\Command\\Input;
use Marko\\Core\\Command\\Output;

#[Command(name: 'test:runner', description: 'Test runner command')]
class TestRunnerCommand implements CommandInterface
{
    public function execute(
        Input \$input,
        Output \$output,
    ): int {
        \$output->writeLine('Command executed!');
        return 42;
    }
}
PHP;
    file_put_contents($modulePath . '/src/TestRunnerCommand.php', $commandCode);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    // commandRunner should be available and functional
    $input = new Input([]);
    $output = new Output();

    $exitCode = $app->commandRunner->run('test:runner', $input, $output);

    expect($exitCode)->toBe(42);

    appTestCleanupDirectory($baseDir);
});

it('calls module boot callbacks after bindings are registered', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with a boot callback
    $modulePath = $vendorDir . '/acme/bootable';
    mkdir($modulePath, 0755, true);

    // Create composer.json
    file_put_contents($modulePath . '/composer.json', json_encode([
        'name' => 'acme/bootable',
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
    ], JSON_PRETTY_PRINT));

    // Create module.php with boot callback that sets a flag on the container
    // We'll use a static variable to track if boot was called
    $modulePhpContent = <<<'PHP'
<?php

declare(strict_types=1);

return [
    'boot' => function (\Marko\Core\Container\ContainerInterface $container) {
        // Set a flag that we can check after boot
        $GLOBALS['__marko_boot_test_called'] = true;
    },
];
PHP;
    file_put_contents($modulePath . '/module.php', $modulePhpContent);

    // Clear any previous test state
    unset($GLOBALS['__marko_boot_test_called']);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    // Before boot, the flag should not be set
    expect($GLOBALS['__marko_boot_test_called'] ?? false)->toBeFalse();

    $app->initialize();

    // After boot, the flag should be set
    expect($GLOBALS['__marko_boot_test_called'] ?? false)->toBeTrue();

    // Clean up
    unset($GLOBALS['__marko_boot_test_called']);
    appTestCleanupDirectory($baseDir);
});

it('passes container to boot callbacks', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with a boot callback that uses the container
    $modulePath = $vendorDir . '/acme/bootable';
    mkdir($modulePath, 0755, true);

    file_put_contents($modulePath . '/composer.json', json_encode([
        'name' => 'acme/bootable',
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
    ], JSON_PRETTY_PRINT));

    // Boot callback that stores the container class name in a global
    $modulePhpContent = <<<'PHP'
<?php

declare(strict_types=1);

return [
    'boot' => function (\Marko\Core\Container\ContainerInterface $container) {
        $GLOBALS['__marko_boot_container_class'] = $container::class;
    },
];
PHP;
    file_put_contents($modulePath . '/module.php', $modulePhpContent);

    unset($GLOBALS['__marko_boot_container_class']);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    // The boot callback should have received the container
    expect($GLOBALS['__marko_boot_container_class'] ?? '')->toBe('Marko\Core\Container\Container');

    unset($GLOBALS['__marko_boot_container_class']);
    appTestCleanupDirectory($baseDir);
});

it('auto-injects dependencies into module boot callbacks', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    $modulePath = $vendorDir . '/acme/bootable';
    mkdir($modulePath, 0755, true);

    file_put_contents($modulePath . '/composer.json', json_encode([
        'name' => 'acme/bootable',
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
        'bindings' => [
            'Marko\Core\Path\ProjectPaths' => 'Marko\Core\Path\ProjectPaths',
        ],
    ], JSON_PRETTY_PRINT));

    // Boot callback that declares ProjectPaths as a typed parameter (auto-injected, not positionally passed)
    $modulePhpContent = <<<'PHP'
<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;

return [
    'boot' => function (ProjectPaths $paths) {
        $GLOBALS['__marko_auto_inject_paths'] = $paths::class;
    },
];
PHP;
    file_put_contents($modulePath . '/module.php', $modulePhpContent);

    unset($GLOBALS['__marko_auto_inject_paths']);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    expect($GLOBALS['__marko_auto_inject_paths'] ?? '')->toBe('Marko\Core\Path\ProjectPaths');

    unset($GLOBALS['__marko_auto_inject_paths']);
    appTestCleanupDirectory($baseDir);
});

it('continues to work with boot callbacks that receive ContainerInterface', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    $modulePath = $vendorDir . '/acme/bootable';
    mkdir($modulePath, 0755, true);

    file_put_contents($modulePath . '/composer.json', json_encode([
        'name' => 'acme/bootable',
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
    ], JSON_PRETTY_PRINT));

    $modulePhpContent = <<<'PHP'
<?php

declare(strict_types=1);

return [
    'boot' => function (\Marko\Core\Container\ContainerInterface $container) {
        $GLOBALS['__marko_ci_boot_class'] = $container::class;
    },
];
PHP;
    file_put_contents($modulePath . '/module.php', $modulePhpContent);

    unset($GLOBALS['__marko_ci_boot_class']);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    expect($GLOBALS['__marko_ci_boot_class'] ?? '')->toBe('Marko\Core\Container\Container');

    unset($GLOBALS['__marko_ci_boot_class']);
    appTestCleanupDirectory($baseDir);
});

it('runs boot callbacks after all framework services are registered', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    $modulePath = $vendorDir . '/acme/bootable';
    mkdir($modulePath, 0755, true);

    file_put_contents($modulePath . '/composer.json', json_encode([
        'name' => 'acme/bootable',
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
    ], JSON_PRETTY_PRINT));

    // Boot callback that resolves EventDispatcherInterface — a service registered
    // late in the boot sequence. This verifies boot callbacks run after the full
    // container is assembled, not partway through.
    $modulePhpContent = <<<'PHP'
<?php

declare(strict_types=1);

return [
    'boot' => function (\Marko\Core\Event\EventDispatcherInterface $dispatcher) {
        $GLOBALS['__marko_boot_order_test'] = $dispatcher::class;
    },
];
PHP;
    file_put_contents($modulePath . '/module.php', $modulePhpContent);

    unset($GLOBALS['__marko_boot_order_test']);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    expect($GLOBALS['__marko_boot_order_test'] ?? '')->toBe('Marko\Core\Event\EventDispatcher');

    unset($GLOBALS['__marko_boot_order_test']);
    appTestCleanupDirectory($baseDir);
});

it('registers the container as an instance of ContainerInterface', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    $resolved = $app->container->get(ContainerInterface::class);

    expect($resolved)->toBe($app->container);

    appTestCleanupDirectory($baseDir);
});

it('loads Application class without marko/routing installed (no Router type fatal)', function (): void {
    // This test verifies that instantiating Application does not cause a fatal
    // error due to PHP resolving the Router type at class-load time.
    // The property types must be ?object / object, not ?Router / Router.
    $app = new Application();

    expect($app)->toBeInstanceOf(Application::class);
});

it('stores router as nullable object property', function (): void {
    $reflection = new ReflectionClass(Application::class);
    $property = $reflection->getProperty('_router');

    // The backing property must be typed as ?object (not ?Router)
    expect($property->getType()->getName())->toBe('object')
        ->and($property->getType()->allowsNull())->toBeTrue();
});

it('throws RuntimeException when accessing router property without routing installed', function (): void {
    $app = new Application();

    expect(fn () => $app->router)
        ->toThrow(
            RuntimeException::class,
            'Router not available. Install marko/routing: composer require marko/routing',
        );
});

it('still assigns Router instance correctly when routing is available', function (): void {
    // When routing IS available (it is in the test environment), booting the
    // app should populate $_router so $app->router returns an object.
    $baseDir = sys_get_temp_dir() . '/marko_router_test_' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';
    mkdir($vendorDir, 0o777, true);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    // marko/routing IS a dev dependency, so the router must be set after boot
    expect($app->router)->toBeObject();

    appTestCleanupDirectory($baseDir);
});

it('has an initialize() method that performs all discovery and wiring', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    expect($app->modules)->toHaveCount(1)
        ->and($app->container)->toBeInstanceOf(ContainerInterface::class);

    appTestCleanupDirectory($baseDir);
});

it('no longer has a public boot() instance method', function (): void {
    $reflection = new ReflectionClass(Application::class);

    $hasPublicInstanceBoot = $reflection->hasMethod('boot')
        && $reflection->getMethod('boot')->isPublic()
        && !$reflection->getMethod('boot')->isStatic();

    expect($hasPublicInstanceBoot)->toBeFalse();
});

it('includes installation instructions in router error message', function (): void {
    $app = new Application();

    expect(fn () => $app->router)->toThrow(
        RuntimeException::class,
        'Router not available. Install marko/routing: composer require marko/routing',
    );
});

it('bootstrap.php calls initialize() instead of boot()', function (): void {
    $bootstrapPath = dirname(__DIR__, 2) . '/bootstrap.php';
    $content = file_get_contents($bootstrapPath);

    expect($content)->toContain('$app->initialize()')
        ->and($content)->not->toContain('$app->boot()');
});

it('CliKernel.php calls initialize() instead of boot()', function (): void {
    $cliKernelPath = realpath(dirname(__DIR__, 3) . '/cli/src/CliKernel.php');
    $content = file_get_contents($cliKernelPath);

    expect($content)->toContain('$app->initialize()')
        ->and($content)->not->toContain('$app->boot()');
});

it('creates an application with inferred paths from base path using Application::boot()', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-boot-test-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $app = Application::boot($baseDir);

    expect($app)->toBeInstanceOf(Application::class);

    appTestCleanupDirectory($baseDir);
});

it('sets vendorPath to basePath/vendor', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-boot-test-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $app = Application::boot($baseDir);

    expect($app->vendorPath)->toBe($baseDir . '/vendor');

    appTestCleanupDirectory($baseDir);
});

it('sets modulesPath to basePath/modules', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-boot-test-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $app = Application::boot($baseDir);

    expect($app->modulesPath)->toBe($baseDir . '/modules');

    appTestCleanupDirectory($baseDir);
});

it('sets appPath to basePath/app', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-boot-test-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $app = Application::boot($baseDir);

    expect($app->appPath)->toBe($baseDir . '/app');

    appTestCleanupDirectory($baseDir);
});

it('calls initialize() during boot() so the application is fully initialized', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-boot-test-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $app = Application::boot($baseDir);

    // After boot(), container and registries should be initialized
    expect($app->container)->not->toBeNull()
        ->and($app->preferenceRegistry)->not->toBeNull()
        ->and($app->observerRegistry)->not->toBeNull()
        ->and($app->eventDispatcher)->not->toBeNull();

    appTestCleanupDirectory($baseDir);
});

it('returns the Application instance (return type self)', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-boot-test-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $result = Application::boot($baseDir);

    expect($result)->toBeInstanceOf(Application::class);

    appTestCleanupDirectory($baseDir);
});

it('throws RuntimeException when basePath is not a valid directory', function (): void {
    expect(fn () => Application::boot('/non/existent/path'))
        ->toThrow(RuntimeException::class, 'Base path does not exist: /non/existent/path');
});

it('throws RuntimeException with helpful message when routing package is not installed', function (): void {
    $app = new Application();

    expect(fn () => $app->handleRequest())
        ->toThrow(RuntimeException::class, 'Cannot handle HTTP requests: marko/routing is not installed. Run: composer require marko/routing');
});

it('creates a request from globals and routes it through the router', function (): void {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';

    $handledRequest = null;
    $mockRouter = new class ($handledRequest)
    {
        public function __construct(
            public mixed &$capturedRequest,
        ) {}

        public function handle(Request $request): Response
        {
            $this->capturedRequest = $request;

            return new Response('hello');
        }
    };

    $app = new Application();
    $reflection = new ReflectionClass($app);
    $reflection->getProperty('_router')->setValue($app, $mockRouter);

    ob_start();
    $app->handleRequest();
    ob_get_clean();

    expect($mockRouter->capturedRequest)->toBeInstanceOf(Request::class);
});

it('sends the response after routing', function (): void {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';

    $mockRouter = new class ()
    {
        public function handle(Request $request): Response
        {
            return new Response('response body');
        }
    };

    $app = new Application();
    $reflection = new ReflectionClass($app);
    $reflection->getProperty('_router')->setValue($app, $mockRouter);

    ob_start();
    $app->handleRequest();
    $output = ob_get_clean();

    expect($output)->toBe('response body');
});

it('returns void', function (): void {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';

    $mockRouter = new class ()
    {
        public function handle(Request $request): Response
        {
            return new Response('');
        }
    };

    $app = new Application();
    $reflection = new ReflectionClass($app);
    $reflection->getProperty('_router')->setValue($app, $mockRouter);

    ob_start();
    $result = $app->handleRequest();
    ob_get_clean();

    $reflectionMethod = $reflection->getMethod('handleRequest');

    expect($result)->toBeNull()
        ->and($reflectionMethod->getReturnType()->getName())->toBe('void');
});

it('creates PluginInterceptor and injects it into Container via setter during initialization', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    appTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    // Use reflection to verify the container has a PluginInterceptor set
    $reflection = new ReflectionClass($app->container);
    $property = $reflection->getProperty('pluginInterceptor');
    $interceptor = $property->getValue($app->container);

    expect($interceptor)->toBeInstanceOf(PluginInterceptor::class);

    appTestCleanupDirectory($baseDir);
});

it('uses the same PluginRegistry instance for both discovery and interception', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');
    mkdir($modulePath . '/src', 0755, true);

    $targetCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeSameRegistry$uniqueId;

class TargetClass$uniqueId
{
    public function doSomething(): string
    {
        return 'original';
    }
}
PHP;
    file_put_contents($modulePath . '/src/TargetClass.php', $targetCode);

    $pluginCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeSameRegistry$uniqueId;

use Marko\\Core\\Attributes\\Plugin;
use Marko\\Core\\Attributes\\Before;

#[Plugin(target: TargetClass$uniqueId::class)]
class TargetPlugin$uniqueId
{
    #[Before]
    public function beforeDoSomething(): void {}
}
PHP;
    file_put_contents($modulePath . '/src/TargetPlugin.php', $pluginCode);

    require_once $modulePath . '/src/TargetClass.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    // The registry on the app and the one inside the interceptor must be the same instance
    $containerReflection = new ReflectionClass($app->container);
    $interceptorProp = $containerReflection->getProperty('pluginInterceptor');
    $interceptor = $interceptorProp->getValue($app->container);

    $interceptorReflection = new ReflectionClass($interceptor);
    $registryProp = $interceptorReflection->getProperty('registry');
    $interceptorRegistry = $registryProp->getValue($interceptor);

    expect($interceptorRegistry)->toBe($app->pluginRegistry);

    appTestCleanupDirectory($baseDir);
});

it('resolves objects with plugin interception after full initialization', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');
    mkdir($modulePath . '/src', 0755, true);

    $targetCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeIntercept$uniqueId;

class GreetingService$uniqueId
{
    public function greet(): string
    {
        return 'hello';
    }
}
PHP;
    file_put_contents($modulePath . '/src/GreetingService.php', $targetCode);

    $pluginCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeIntercept$uniqueId;

use Marko\\Core\\Attributes\\Plugin;
use Marko\\Core\\Attributes\\After;

#[Plugin(target: GreetingService$uniqueId::class)]
class GreetingPlugin$uniqueId
{
    #[After]
    public function greet(string \$result): string
    {
        return \$result . ' world';
    }
}
PHP;
    file_put_contents($modulePath . '/src/GreetingPlugin.php', $pluginCode);

    require_once $modulePath . '/src/GreetingService.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    $targetClass = "AcmeIntercept$uniqueId\\GreetingService$uniqueId";
    $service = $app->container->get($targetClass);

    expect($service->greet())->toBe('hello world');

    appTestCleanupDirectory($baseDir);
});

it('creates PluginRegistry before plugin discovery and reuses it for PluginInterceptor', function (): void {
    $uniqueId = bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    $modulePath = $vendorDir . '/acme/core';
    appTestCreateModule($modulePath, 'acme/core');
    mkdir($modulePath . '/src', 0755, true);

    $targetCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePreCreate$uniqueId;

class ServiceClass$uniqueId
{
    public function run(): string
    {
        return 'original';
    }
}
PHP;
    file_put_contents($modulePath . '/src/ServiceClass.php', $targetCode);

    $pluginCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmePreCreate$uniqueId;

use Marko\\Core\\Attributes\\Plugin;
use Marko\\Core\\Attributes\\After;

#[Plugin(target: ServiceClass$uniqueId::class)]
class ServicePlugin$uniqueId
{
    #[After]
    public function run(string \$result): string
    {
        return \$result . '-modified';
    }
}
PHP;
    file_put_contents($modulePath . '/src/ServicePlugin.php', $pluginCode);

    require_once $modulePath . '/src/ServiceClass.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->initialize();

    $targetClass = "AcmePreCreate$uniqueId\\ServiceClass$uniqueId";

    // The registry used at discovery time must have the plugin,
    // and the same instance must power interception at resolve time
    expect($app->pluginRegistry->hasPluginsFor($targetClass))->toBeTrue();

    $service = $app->container->get($targetClass);
    expect($service->run())->toBe('original-modified');

    appTestCleanupDirectory($baseDir);
});

it('includes SessionMiddleware in global middleware', function (): void {
    $reflection = new ReflectionClass(Application::class);
    $constant = $reflection->getReflectionConstant('GLOBAL_MIDDLEWARE');

    expect($constant->getValue())->toContain('Marko\\Session\\Middleware\\SessionMiddleware');
});

it('includes LayoutMiddleware in global middleware', function (): void {
    $reflection = new ReflectionClass(Application::class);
    $constant = $reflection->getReflectionConstant('GLOBAL_MIDDLEWARE');

    expect($constant->getValue())->toContain('Marko\\Layout\\Middleware\\LayoutMiddleware');
});
