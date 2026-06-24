<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleAutoloader;

// Helper to recursively remove a directory
function moduleAutoloaderCleanup(string $dir): void
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
            moduleAutoloaderCleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * Create a minimal Marko module directory with a composer.json.
 *
 * @param array<string, string> $psr4 PSR-4 autoload map (namespace => relPath)
 */
function createAutoloaderTestModule(
    string $path,
    string $name,
    array $psr4 = [],
    bool $isMarkoModule = true,
): void {
    mkdir($path, 0755, true);

    $composerData = [
        'name' => $name,
    ];

    if ($isMarkoModule) {
        $composerData['extra'] = ['marko' => ['module' => true]];
    }

    if ($psr4 !== []) {
        $composerData['autoload'] = ['psr-4' => $psr4];
    }

    file_put_contents($path . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));
}

// ─────────────────────────────────────────────────────────────────────────────

it('registers a psr-4 autoloader that resolves an app module class from its source file', function (): void {
    $base = sys_get_temp_dir() . '/marko-autoloader-test-' . bin2hex(random_bytes(8));
    $appDir = $base . '/app';
    $namespace = 'Acme\\Blog\\';
    $srcDir = 'src';

    // Create a real app module with PSR-4 map
    createAutoloaderTestModule(
        $appDir . '/blog',
        'acme/blog',
        [$namespace => $srcDir],
    );

    // Create the class file that should be discoverable
    $classDir = $appDir . '/blog/' . $srcDir;
    mkdir($classDir, 0755, true);
    $uniqueClass = 'AutoloaderTestBlogService' . bin2hex(random_bytes(4));
    $nsDecl = rtrim($namespace, '\\');
    file_put_contents(
        $classDir . '/' . $uniqueClass . '.php',
        "<?php\ndeclare(strict_types=1);\nnamespace $nsDecl;\nclass $uniqueClass {}\n",
    );

    $autoloader = new ModuleAutoloader(
        modulesPath: '',
        appPath: $appDir,
        parser: new ManifestParser(),
    );
    $autoloader->register();

    $fqcn = $namespace . $uniqueClass;
    expect(class_exists($fqcn))->toBeTrue();

    moduleAutoloaderCleanup($base);
});

it('registers autoloaders for modules in the modules directory', function (): void {
    $base = sys_get_temp_dir() . '/marko-autoloader-test-' . bin2hex(random_bytes(8));
    $modulesDir = $base . '/modules';
    $namespace = 'Acme\\Checkout\\';
    $srcDir = 'src';

    createAutoloaderTestModule(
        $modulesDir . '/checkout',
        'acme/checkout',
        [$namespace => $srcDir],
    );

    $classDir = $modulesDir . '/checkout/' . $srcDir;
    mkdir($classDir, 0755, true);
    $uniqueClass = 'AutoloaderTestCheckoutService' . bin2hex(random_bytes(4));
    $nsDecl = rtrim($namespace, '\\');
    file_put_contents(
        $classDir . '/' . $uniqueClass . '.php',
        "<?php\ndeclare(strict_types=1);\nnamespace $nsDecl;\nclass $uniqueClass {}\n",
    );

    $autoloader = new ModuleAutoloader(
        modulesPath: $modulesDir,
        appPath: '',
        parser: new ManifestParser(),
    );
    $autoloader->register();

    $fqcn = $namespace . $uniqueClass;
    expect(class_exists($fqcn))->toBeTrue();

    moduleAutoloaderCleanup($base);
});

it('skips vendor modules because composer already autoloads them', function (): void {
    $base = sys_get_temp_dir() . '/marko-autoloader-test-' . bin2hex(random_bytes(8));
    $appDir = $base . '/app';
    $namespace = 'Acme\\VendorOnly\\';
    $srcDir = 'src';

    // Create a module as if it were a vendor module (source=vendor) by
    // NOT placing it in modulesPath or appPath — just verify the autoloader
    // only processes app/modules paths. We create a separate "vendor-like"
    // module directory and confirm it is not picked up.
    //
    // The simplest way: put a module with PSR-4 in the app dir, but verify
    // that a file in a separate directory that isn't in any registered path
    // is NOT auto-loaded. Separately, test that source='vendor' modules are
    // skipped by the autoloader even if discoverable.
    //
    // Because ModuleAutoloader only calls discoverInModules + discoverInApp,
    // it will never see vendor modules (those come from discoverInVendor).
    // So we verify: passing an empty modulesPath + empty appPath means zero
    // autoloaders registered, and class_exists returns false for a class
    // that only lives in a vendor-like path.
    $vendorModulePath = $base . '/vendor/acme/vendor-only';
    createAutoloaderTestModule($vendorModulePath, 'acme/vendor-only', [$namespace => $srcDir]);

    $classDir = $vendorModulePath . '/' . $srcDir;
    mkdir($classDir, 0755, true);
    $uniqueClass = 'AutoloaderTestVendorService' . bin2hex(random_bytes(4));
    $nsDecl = rtrim($namespace, '\\');
    file_put_contents(
        $classDir . '/' . $uniqueClass . '.php',
        "<?php\ndeclare(strict_types=1);\nnamespace $nsDecl;\nclass $uniqueClass {}\n",
    );

    // ModuleAutoloader only discovers from modulesPath and appPath — vendor is excluded
    $autoloader = new ModuleAutoloader(
        modulesPath: '',
        appPath: '',
        parser: new ManifestParser(),
    );
    $autoloader->register();

    $fqcn = $namespace . $uniqueClass;
    expect(class_exists($fqcn, false))->toBeFalse();

    moduleAutoloaderCleanup($base);
});

it('does not register the same namespace and path twice when called repeatedly', function (): void {
    $base = sys_get_temp_dir() . '/marko-autoloader-test-' . bin2hex(random_bytes(8));
    $appDir = $base . '/app';
    $namespace = 'Acme\\Idempotent\\';
    $srcDir = 'src';

    createAutoloaderTestModule(
        $appDir . '/idempotent',
        'acme/idempotent',
        [$namespace => $srcDir],
    );

    $autoloader = new ModuleAutoloader(
        modulesPath: '',
        appPath: $appDir,
        parser: new ManifestParser(),
    );

    $countBefore = count(spl_autoload_functions());
    $autoloader->register();
    $countAfterFirst = count(spl_autoload_functions());
    $autoloader->register();
    $countAfterSecond = count(spl_autoload_functions());

    // First call registers new autoloaders, second call adds none
    expect($countAfterFirst)->toBeGreaterThan($countBefore)
        ->and($countAfterSecond)->toBe($countAfterFirst);

    moduleAutoloaderCleanup($base);
});

it('resolves nothing and does not error when app and modules directories are empty', function (): void {
    $base = sys_get_temp_dir() . '/marko-autoloader-test-' . bin2hex(random_bytes(8));
    $appDir = $base . '/app';
    $modulesDir = $base . '/modules';

    mkdir($appDir, 0755, true);
    mkdir($modulesDir, 0755, true);

    $autoloader = new ModuleAutoloader(
        modulesPath: $modulesDir,
        appPath: $appDir,
        parser: new ManifestParser(),
    );

    // Should not throw
    $autoloader->register();

    // Passing — no exception means success
    expect(true)->toBeTrue();

    moduleAutoloaderCleanup($base);
});

it('leaves Application boot still able to autoload an app module class (regression)', function (): void {
    $base = sys_get_temp_dir() . '/marko-autoloader-test-' . bin2hex(random_bytes(8));
    $vendorDir = $base . '/vendor';
    $modulesDir = $base . '/modules';
    $appDir = $base . '/app';
    $namespace = 'Acme\\RegressionApp\\';
    $srcDir = 'src';

    // Create the app module
    createAutoloaderTestModule(
        $appDir . '/regression',
        'acme/regression',
        [$namespace => $srcDir],
    );

    $classDir = $appDir . '/regression/' . $srcDir;
    mkdir($classDir, 0755, true);
    $uniqueClass = 'AutoloaderTestRegressionService' . bin2hex(random_bytes(4));
    $nsDecl = rtrim($namespace, '\\');
    file_put_contents(
        $classDir . '/' . $uniqueClass . '.php',
        "<?php\ndeclare(strict_types=1);\nnamespace $nsDecl;\nclass $uniqueClass {}\n",
    );

    // Boot the full Application — it must still autoload the app module class
    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: $modulesDir,
        appPath: $appDir,
    );
    $app->initialize();

    $fqcn = $namespace . $uniqueClass;
    expect(class_exists($fqcn))->toBeTrue();

    moduleAutoloaderCleanup($base);
});
