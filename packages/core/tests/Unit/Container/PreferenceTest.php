<?php

declare(strict_types=1);

use Marko\Core\Attributes\Preference;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Exceptions\PreferenceConflictException;
use Marko\Core\Module\ModuleManifest;

// Helper function for recursive directory cleanup
function cleanupPreferenceTestDirectory(
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
            cleanupPreferenceTestDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

it('creates Preference attribute with replaces parameter', function (): void {
    $preference = new Preference(replaces: 'App\Services\UserService');

    expect($preference->replaces)->toBe('App\Services\UserService');
});

it('discovers classes with Preference attribute in module src directories', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-preference-test-' . uniqid();
    mkdir($tempDir . '/src/Preference', 0755, true);

    // Create a preference class file
    $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace Acme\Blog\Preference;

use Marko\Core\Attributes\Preference;

#[Preference(replaces: \stdClass::class)]
class CustomStdClass extends \stdClass
{
}

PHP;
    file_put_contents($tempDir . '/src/Preference/CustomStdClass.php', $content);

    $manifest = new ModuleManifest(
        name: 'acme/blog',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new PreferenceDiscovery();
    $preferenceFiles = $discovery->discoverInModule($manifest);

    expect($preferenceFiles)
        ->toBeArray()
        ->toHaveCount(1)
        ->and($preferenceFiles[0])->toEndWith('CustomStdClass.php');

    cleanupPreferenceTestDirectory($tempDir);
});

// Test fixtures for registry tests
class OriginalService {}
class PreferredService extends OriginalService {}
class FinalPreferredService extends PreferredService {}

it('registers preference as class to class mapping', function (): void {
    $registry = new PreferenceRegistry();

    $registry->register(
        original: OriginalService::class,
        replacement: PreferredService::class,
    );

    expect($registry->getPreference(OriginalService::class))
        ->toBe(PreferredService::class);
});

it('resolves original class request to preference class', function (): void {
    $registry = new PreferenceRegistry();
    $registry->register(
        original: OriginalService::class,
        replacement: PreferredService::class,
    );

    $container = new Container($registry);
    $instance = $container->get(OriginalService::class);

    expect($instance)->toBeInstanceOf(PreferredService::class);
});

it('chains preferences when A replaces B and C replaces A', function (): void {
    $registry = new PreferenceRegistry();
    // PreferredService replaces OriginalService
    $registry->register(
        original: OriginalService::class,
        replacement: PreferredService::class,
    );
    // FinalPreferredService replaces PreferredService
    $registry->register(
        original: PreferredService::class,
        replacement: FinalPreferredService::class,
    );

    $container = new Container($registry);
    $instance = $container->get(OriginalService::class);

    // Should resolve to FinalPreferredService through the chain
    expect($instance)->toBeInstanceOf(FinalPreferredService::class);
});

it('throws PreferenceConflictException when two same-priority modules prefer the same class', function (): void {
    $registry = new PreferenceRegistry();

    $registry->register(
        original: OriginalService::class,
        replacement: PreferredService::class,
        moduleName: 'vendor/module-a',
        moduleSource: 'vendor',
    );

    $registry->register(
        original: OriginalService::class,
        replacement: FinalPreferredService::class,
        moduleName: 'vendor/module-b',
        moduleSource: 'vendor',
    );
})->throws(PreferenceConflictException::class, 'Multiple modules define a Preference for the same class');

it('allows higher-priority module to override lower-priority preference', function (): void {
    $registry = new PreferenceRegistry();

    $registry->register(
        original: OriginalService::class,
        replacement: PreferredService::class,
        moduleName: 'acme/blog',
        moduleSource: 'vendor',
    );

    $registry->register(
        original: OriginalService::class,
        replacement: FinalPreferredService::class,
        moduleName: 'app/blog',
        moduleSource: 'app',
    );

    expect($registry->getPreference(OriginalService::class))
        ->toBe(FinalPreferredService::class);
});

it('ignores lower-priority preference when higher-priority already registered', function (): void {
    $registry = new PreferenceRegistry();

    $registry->register(
        original: OriginalService::class,
        replacement: FinalPreferredService::class,
        moduleName: 'app/blog',
        moduleSource: 'app',
    );

    $registry->register(
        original: OriginalService::class,
        replacement: PreferredService::class,
        moduleName: 'acme/blog',
        moduleSource: 'vendor',
    );

    expect($registry->getPreference(OriginalService::class))
        ->toBe(FinalPreferredService::class);
});
