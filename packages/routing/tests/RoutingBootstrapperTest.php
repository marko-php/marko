<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\RouteCollection;
use Marko\Routing\Router;

// Helper function for recursive directory cleanup
function routingTestCleanupDirectory(
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
            routingTestCleanupDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Helper to create a test module with composer.json and optional module.php
function routingTestCreateModule(
    string $path,
    string $name,
    string $version = '1.0.0',
    array $require = [],
    ?array $modulePhp = null,
    ?array $autoload = null,
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
    if ($autoload !== null) {
        $composerData['autoload'] = $autoload;
    }
    file_put_contents($path . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create module.php (optional)
    if ($modulePhp !== null) {
        $modulePhpContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($modulePhp, true) . ";\n";
        file_put_contents($path . '/module.php', $modulePhpContent);
    }
}

it('registers Router in container during boot', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a minimal module
    routingTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $container = $app->container;

    expect($container->has(Router::class))->toBeTrue();

    routingTestCleanupDirectory($baseDir);
});

it('discovers routes from all loaded modules', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create a module with a controller
    $modulePath = $vendorDir . '/acme/blog';
    routingTestCreateModule(
        $modulePath,
        'acme/blog',
        '1.0.0',
        [],
        null,
        ['psr-4' => ["AcmeBlog{$uniqueId}\\" => 'src/']],
    );

    // Create a controller with route attributes
    mkdir($modulePath . '/src', 0755, true);
    $controllerCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeBlog{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class BlogController
{
    #[Get('/posts')]
    public function index(): string
    {
        return 'posts';
    }
}
PHP;
    file_put_contents($modulePath . '/src/BlogController.php', $controllerCode);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $router = $app->router;
    $routes = $app->container->get(RouteCollection::class);

    expect($routes->count())->toBeGreaterThan(0)
        ->and($routes->has('GET', '/posts'))->toBeTrue();

    routingTestCleanupDirectory($baseDir);
});

it('resolves RouteCollection through container', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    routingTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $container = $app->container;
    $routes = $container->get(RouteCollection::class);

    expect($routes)->toBeInstanceOf(RouteCollection::class);

    routingTestCleanupDirectory($baseDir);
});

it('applies Preference inheritance during discovery', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';
    $appDir = $baseDir . '/app';

    // Create vendor module with a controller
    $vendorModulePath = $vendorDir . '/acme/blog';
    routingTestCreateModule(
        $vendorModulePath,
        'acme/blog',
        '1.0.0',
        [],
        null,
        ['psr-4' => ["AcmeBlog{$uniqueId}\\" => 'src/']],
    );

    // Create vendor controller
    mkdir($vendorModulePath . '/src', 0755, true);
    $vendorControllerCode = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeBlog{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class BlogController
{
    #[Get('/blog')]
    public function index(): string
    {
        return 'vendor blog';
    }
}
PHP;
    file_put_contents($vendorModulePath . '/src/BlogController.php', $vendorControllerCode);

    // Create app module that overrides the controller with Preference
    $appModulePath = $appDir . '/blog';
    routingTestCreateModule(
        $appModulePath,
        'app/blog',
        '1.0.0',
        ['acme/blog' => '^1.0'],
        null,
        ['psr-4' => ["AppBlog{$uniqueId}\\" => 'src/']],
    );

    // Create app controller with Preference
    mkdir($appModulePath . '/src', 0755, true);
    $appControllerCode = <<<PHP
<?php

declare(strict_types=1);

namespace AppBlog{$uniqueId};

use AcmeBlog{$uniqueId}\\BlogController as VendorBlogController;
use Marko\\Core\\Attributes\\Preference;
use Marko\\Routing\\Attributes\\InheritRoute;

#[Preference(replaces: VendorBlogController::class)]
class BlogController extends VendorBlogController
{
    #[InheritRoute]
    public function index(): string
    {
        return 'app blog';
    }
}
PHP;
    file_put_contents($appModulePath . '/src/BlogController.php', $appControllerCode);

    // Pre-load the vendor controller
    require_once $vendorModulePath . '/src/BlogController.php';

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: $appDir,
    );

    $app->boot();

    $routes = $app->container->get(RouteCollection::class);
    $route = $routes->get('GET', '/blog');

    // The route should use the app controller (the Preference)
    expect($route)->not->toBeNull()
        ->and($route->controller)->toBe("AppBlog{$uniqueId}\\BlogController");

    routingTestCleanupDirectory($baseDir);
});

it('provides getRouter method on Application', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    routingTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $router = $app->router;

    expect($router)->toBeInstanceOf(Router::class);

    routingTestCleanupDirectory($baseDir);
});

it('returns Router as singleton in container', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    routingTestCreateModule($vendorDir . '/acme/core', 'acme/core');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $container = $app->container;
    $router1 = $container->get(Router::class);
    $router2 = $container->get(Router::class);

    expect($router1)->toBe($router2);

    routingTestCleanupDirectory($baseDir);
});

it('runs route discovery after module loading', function (): void {
    // This test ensures routes are discovered from modules loaded in order
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create module A
    $moduleAPath = $vendorDir . '/acme/module-a';
    routingTestCreateModule(
        $moduleAPath,
        'acme/module-a',
        '1.0.0',
        [],
        null,
        ['psr-4' => ["AcmeModuleA{$uniqueId}\\" => 'src/']],
    );

    mkdir($moduleAPath . '/src', 0755, true);
    $controllerA = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeModuleA{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class ControllerA
{
    #[Get('/module-a')]
    public function index(): string
    {
        return 'module A';
    }
}
PHP;
    file_put_contents($moduleAPath . '/src/ControllerA.php', $controllerA);

    // Create module B that depends on A
    $moduleBPath = $vendorDir . '/acme/module-b';
    routingTestCreateModule(
        $moduleBPath,
        'acme/module-b',
        '1.0.0',
        ['acme/module-a' => '^1.0'],
        null,
        ['psr-4' => ["AcmeModuleB{$uniqueId}\\" => 'src/']],
    );

    mkdir($moduleBPath . '/src', 0755, true);
    $controllerB = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeModuleB{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class ControllerB
{
    #[Get('/module-b')]
    public function index(): string
    {
        return 'module B';
    }
}
PHP;
    file_put_contents($moduleBPath . '/src/ControllerB.php', $controllerB);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    $app->boot();

    $routes = $app->container->get(RouteCollection::class);

    // Both routes should be discovered
    expect($routes->has('GET', '/module-a'))->toBeTrue()
        ->and($routes->has('GET', '/module-b'))->toBeTrue();

    routingTestCleanupDirectory($baseDir);
});

it('detects route conflicts during boot and fails fast', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';

    // Create module A with a route
    $moduleAPath = $vendorDir . '/acme/module-a';
    routingTestCreateModule(
        $moduleAPath,
        'acme/module-a',
        '1.0.0',
        [],
        null,
        ['psr-4' => ["AcmeConflictA{$uniqueId}\\" => 'src/']],
    );

    mkdir($moduleAPath . '/src', 0755, true);
    $controllerA = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeConflictA{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class ControllerA
{
    #[Get('/conflict')]
    public function index(): string
    {
        return 'A';
    }
}
PHP;
    file_put_contents($moduleAPath . '/src/ControllerA.php', $controllerA);

    // Create module B with the same route (conflict!)
    $moduleBPath = $vendorDir . '/acme/module-b';
    routingTestCreateModule(
        $moduleBPath,
        'acme/module-b',
        '1.0.0',
        [],
        null,
        ['psr-4' => ["AcmeConflictB{$uniqueId}\\" => 'src/']],
    );

    mkdir($moduleBPath . '/src', 0755, true);
    $controllerB = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeConflictB{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class ControllerB
{
    #[Get('/conflict')]
    public function index(): string
    {
        return 'B';
    }
}
PHP;
    file_put_contents($moduleBPath . '/src/ControllerB.php', $controllerB);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    expect(fn () => $app->boot())->toThrow(RouteConflictException::class);

    routingTestCleanupDirectory($baseDir);
});

it('discovers routes from vendor, modules, and app directories', function (): void {
    $uniqueId = uniqid();
    $baseDir = sys_get_temp_dir() . '/marko-routing-test-' . $uniqueId;
    $vendorDir = $baseDir . '/vendor';
    $modulesDir = $baseDir . '/modules';
    $appDir = $baseDir . '/app';

    // Create vendor module
    $vendorModulePath = $vendorDir . '/acme/core';
    routingTestCreateModule(
        $vendorModulePath,
        'acme/core',
        '1.0.0',
        [],
        null,
        ['psr-4' => ["AcmeCore{$uniqueId}\\" => 'src/']],
    );

    mkdir($vendorModulePath . '/src', 0755, true);
    $vendorController = <<<PHP
<?php

declare(strict_types=1);

namespace AcmeCore{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class CoreController
{
    #[Get('/core')]
    public function index(): string
    {
        return 'core';
    }
}
PHP;
    file_put_contents($vendorModulePath . '/src/CoreController.php', $vendorController);

    // Create modules directory module
    $modulesModulePath = $modulesDir . '/custom-checkout';
    routingTestCreateModule(
        $modulesModulePath,
        'custom/checkout',
        '1.0.0',
        ['acme/core' => '^1.0'],
        null,
        ['psr-4' => ["CustomCheckout{$uniqueId}\\" => 'src/']],
    );

    mkdir($modulesModulePath . '/src', 0755, true);
    $modulesController = <<<PHP
<?php

declare(strict_types=1);

namespace CustomCheckout{$uniqueId};

use Marko\\Routing\\Attributes\\Post;

class CheckoutController
{
    #[Post('/checkout')]
    public function process(): string
    {
        return 'checkout';
    }
}
PHP;
    file_put_contents($modulesModulePath . '/src/CheckoutController.php', $modulesController);

    // Create app module
    $appModulePath = $appDir . '/blog';
    routingTestCreateModule(
        $appModulePath,
        'app/blog',
        '1.0.0',
        ['acme/core' => '^1.0'],
        null,
        ['psr-4' => ["AppBlog{$uniqueId}\\" => 'src/']],
    );

    mkdir($appModulePath . '/src', 0755, true);
    $appController = <<<PHP
<?php

declare(strict_types=1);

namespace AppBlog{$uniqueId};

use Marko\\Routing\\Attributes\\Get;

class BlogController
{
    #[Get('/blog')]
    public function index(): string
    {
        return 'blog';
    }
}
PHP;
    file_put_contents($appModulePath . '/src/BlogController.php', $appController);

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: $modulesDir,
        appPath: $appDir,
    );

    $app->boot();

    $routes = $app->container->get(RouteCollection::class);

    // All three routes should be discovered
    expect($routes->has('GET', '/core'))->toBeTrue()
        ->and($routes->has('POST', '/checkout'))->toBeTrue()
        ->and($routes->has('GET', '/blog'))->toBeTrue();

    routingTestCleanupDirectory($baseDir);
});
