<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\Exceptions\TemplateNotFoundException;
use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewConfig;

function createTestViewConfig(
    string $extension = '.latte',
): ViewConfig {
    return new ViewConfig(new FakeConfigRepository([
        'view.extension' => $extension,
    ]));
}

it('ModuleTemplateResolver implements TemplateResolverInterface', function (): void {
    expect(class_exists(ModuleTemplateResolver::class))->toBeTrue()
        ->and(in_array(TemplateResolverInterface::class, class_implements(ModuleTemplateResolver::class)))->toBeTrue();
});

it('ModuleTemplateResolver resolves templates with module prefix', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir . '/resources/views/post', 0755, true);
    file_put_contents($tempDir . '/resources/views/post/show.latte', 'test');

    $modules = [
        new ModuleManifest(
            name: 'vendor/blog',
            version: '1.0.0',
            path: $tempDir,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $result = $resolver->resolve('blog::post/show');

    expect($result)->toBe($tempDir . '/resources/views/post/show.latte');

    // Cleanup
    unlink($tempDir . '/resources/views/post/show.latte');
    rmdir($tempDir . '/resources/views/post');
    rmdir($tempDir . '/resources/views');
    rmdir($tempDir . '/resources');
    rmdir($tempDir);
});

it('ModuleTemplateResolver resolves templates without module prefix', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir . '/resources/views/shared', 0755, true);
    file_put_contents($tempDir . '/resources/views/shared/header.latte', 'header content');

    $modules = [
        new ModuleManifest(
            name: 'vendor/blog',
            version: '1.0.0',
            path: $tempDir,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    // Template without module prefix - searches all modules
    $result = $resolver->resolve('shared/header');

    expect($result)->toBe($tempDir . '/resources/views/shared/header.latte');

    // Cleanup
    unlink($tempDir . '/resources/views/shared/header.latte');
    rmdir($tempDir . '/resources/views/shared');
    rmdir($tempDir . '/resources/views');
    rmdir($tempDir . '/resources');
    rmdir($tempDir);
});

it('ModuleTemplateResolver searches in module priority order', function (): void {
    // app > modules > vendor (app overrides modules, modules override vendor)
    $vendorDir = sys_get_temp_dir() . '/marko-test-vendor-' . uniqid();
    $modulesDir = sys_get_temp_dir() . '/marko-test-modules-' . uniqid();
    $appDir = sys_get_temp_dir() . '/marko-test-app-' . uniqid();

    // Create all directories with templates
    mkdir($vendorDir . '/resources/views/post', 0755, true);
    mkdir($modulesDir . '/resources/views/post', 0755, true);
    mkdir($appDir . '/resources/views/post', 0755, true);

    file_put_contents($vendorDir . '/resources/views/post/show.latte', 'vendor');
    file_put_contents($modulesDir . '/resources/views/post/show.latte', 'modules');
    file_put_contents($appDir . '/resources/views/post/show.latte', 'app');

    // Modules in order: app first, then modules, then vendor
    $modules = [
        new ModuleManifest(
            name: 'app/blog',
            version: '1.0.0',
            path: $appDir,
            source: 'app',
        ),
        new ModuleManifest(
            name: 'custom/blog',
            version: '1.0.0',
            path: $modulesDir,
            source: 'modules',
        ),
        new ModuleManifest(
            name: 'vendor/blog',
            version: '1.0.0',
            path: $vendorDir,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    // getSearchedPaths should return paths in priority order: app > modules > vendor
    $paths = $resolver->getSearchedPaths('blog::post/show');

    expect($paths)->toHaveCount(3)
        ->and($paths[0])->toBe($appDir . '/resources/views/post/show.latte')
        ->and($paths[1])->toBe($modulesDir . '/resources/views/post/show.latte')
        ->and($paths[2])->toBe($vendorDir . '/resources/views/post/show.latte');

    // Cleanup
    unlink($vendorDir . '/resources/views/post/show.latte');
    unlink($modulesDir . '/resources/views/post/show.latte');
    unlink($appDir . '/resources/views/post/show.latte');
    rmdir($vendorDir . '/resources/views/post');
    rmdir($modulesDir . '/resources/views/post');
    rmdir($appDir . '/resources/views/post');
    rmdir($vendorDir . '/resources/views');
    rmdir($modulesDir . '/resources/views');
    rmdir($appDir . '/resources/views');
    rmdir($vendorDir . '/resources');
    rmdir($modulesDir . '/resources');
    rmdir($appDir . '/resources');
    rmdir($vendorDir);
    rmdir($modulesDir);
    rmdir($appDir);
});

it('ModuleTemplateResolver respects app override priority', function (): void {
    // App modules override vendor modules - app template is found first
    $vendorDir = sys_get_temp_dir() . '/marko-test-vendor-' . uniqid();
    $appDir = sys_get_temp_dir() . '/marko-test-app-' . uniqid();

    // Create both directories with templates
    mkdir($vendorDir . '/resources/views/post', 0755, true);
    mkdir($appDir . '/resources/views/post', 0755, true);

    file_put_contents($vendorDir . '/resources/views/post/show.latte', 'vendor');
    file_put_contents($appDir . '/resources/views/post/show.latte', 'app');

    // App module comes first in the repository order (higher priority)
    $modules = [
        new ModuleManifest(
            name: 'app/blog',
            version: '1.0.0',
            path: $appDir,
            source: 'app',
        ),
        new ModuleManifest(
            name: 'vendor/blog',
            version: '1.0.0',
            path: $vendorDir,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    // resolve() should return the app version (first match, highest priority)
    $result = $resolver->resolve('blog::post/show');

    expect($result)->toBe($appDir . '/resources/views/post/show.latte');

    // Cleanup
    unlink($vendorDir . '/resources/views/post/show.latte');
    unlink($appDir . '/resources/views/post/show.latte');
    rmdir($vendorDir . '/resources/views/post');
    rmdir($appDir . '/resources/views/post');
    rmdir($vendorDir . '/resources/views');
    rmdir($appDir . '/resources/views');
    rmdir($vendorDir . '/resources');
    rmdir($appDir . '/resources');
    rmdir($vendorDir);
    rmdir($appDir);
});

it('ModuleTemplateResolver throws TemplateNotFoundException when not found', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir . '/resources/views', 0755, true);

    $modules = [
        new ModuleManifest(
            name: 'vendor/blog',
            version: '1.0.0',
            path: $tempDir,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    expect(fn () => $resolver->resolve('blog::nonexistent/template'))
        ->toThrow(TemplateNotFoundException::class);

    // Cleanup
    rmdir($tempDir . '/resources/views');
    rmdir($tempDir . '/resources');
    rmdir($tempDir);
});

it('ModuleTemplateResolver includes all paths in not found error', function (): void {
    $tempDir1 = sys_get_temp_dir() . '/marko-test-app-' . uniqid();
    $tempDir2 = sys_get_temp_dir() . '/marko-test-vendor-' . uniqid();
    mkdir($tempDir1, 0755, true);
    mkdir($tempDir2, 0755, true);

    $modules = [
        new ModuleManifest(
            name: 'app/blog',
            version: '1.0.0',
            path: $tempDir1,
            source: 'app',
        ),
        new ModuleManifest(
            name: 'vendor/blog',
            version: '1.0.0',
            path: $tempDir2,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $expectedPaths = [
        $tempDir1 . '/resources/views/missing/template.latte',
        $tempDir2 . '/resources/views/missing/template.latte',
    ];

    try {
        $resolver->resolve('blog::missing/template');
        $this->fail('Expected TemplateNotFoundException was not thrown');
    } catch (TemplateNotFoundException $e) {
        // Verify the exception context contains all searched paths
        $context = $e->getContext();
        expect($context)->toContain($expectedPaths[0])
            ->and($context)->toContain($expectedPaths[1]);
    }

    // Cleanup
    rmdir($tempDir1);
    rmdir($tempDir2);
});

it('ModuleTemplateResolver getSearchedPaths returns all paths checked', function (): void {
    $tempDir1 = sys_get_temp_dir() . '/marko-test-1-' . uniqid();
    $tempDir2 = sys_get_temp_dir() . '/marko-test-2-' . uniqid();
    mkdir($tempDir1, 0755, true);
    mkdir($tempDir2, 0755, true);

    $modules = [
        new ModuleManifest(
            name: 'app/blog',
            version: '1.0.0',
            path: $tempDir1,
            source: 'app',
        ),
        new ModuleManifest(
            name: 'vendor/blog',
            version: '1.0.0',
            path: $tempDir2,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $paths = $resolver->getSearchedPaths('blog::post/show');

    // Should return both paths that would be searched
    expect($paths)->toHaveCount(2)
        ->and($paths[0])->toBe($tempDir1 . '/resources/views/post/show.latte')
        ->and($paths[1])->toBe($tempDir2 . '/resources/views/post/show.latte');

    // Cleanup
    rmdir($tempDir1);
    rmdir($tempDir2);
});

it('uses FakeConfigRepository in ModuleTemplateResolverTest', function (): void {
    $repo = new FakeConfigRepository(['view.extension' => '.latte']);
    $viewConfig = new ViewConfig($repo);

    expect($viewConfig->extension())->toBe('.latte');
});
