<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\Inertia\Exceptions\ComponentNotFoundException;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\Rendering\ModuleComponentResolver;
use Marko\Testing\Fake\FakeConfigRepository;

function makeInertiaResolver(string $basePath): ModuleComponentResolver
{
    $modules = new ModuleRepository([
        new ModuleManifest(
            name: 'app/blog',
            version: '1.0.0',
            path: $basePath . '/app/blog',
            source: 'app',
        ),
        new ModuleManifest(
            name: 'marko/admin-panel',
            version: '1.0.0',
            path: $basePath . '/vendor/marko/admin-panel',
            source: 'vendor',
        ),
    ]);

    $config = new InertiaConfig(new FakeConfigRepository([
        'inertia.version' => null,
        'inertia.root.id' => 'app',
        'inertia.root.title' => 'Marko',
        'inertia.page.ensure_pages_exist' => false,
        'inertia.page.paths' => ['resources/js/pages'],
        'inertia.page.extensions' => ['tsx', 'vue'],
        'inertia.testing.ensure_pages_exist' => false,
        'inertia.history.encrypt' => false,
    ]));

    return new ModuleComponentResolver($modules, new ProjectPaths($basePath), $config);
}

it('resolves components from the project root before modules', function (): void {
    $basePath = sys_get_temp_dir() . '/marko-inertia-pages-' . uniqid();
    mkdir($basePath . '/resources/js/pages/Users', 0755, true);
    mkdir($basePath . '/app/blog/resources/js/pages/Users', 0755, true);

    file_put_contents($basePath . '/resources/js/pages/Users/Index.tsx', 'root');
    file_put_contents($basePath . '/app/blog/resources/js/pages/Users/Index.tsx', 'module');

    $resolver = makeInertiaResolver($basePath);

    expect($resolver->resolve('Users/Index'))->toBe($basePath . '/resources/js/pages/Users/Index.tsx')
        ->and($resolver->exists('Users/Index'))->toBeTrue();
});

it('supports module-prefixed component names similar to view resolution', function (): void {
    $basePath = sys_get_temp_dir() . '/marko-inertia-pages-' . uniqid();
    mkdir($basePath . '/vendor/marko/admin-panel/resources/js/pages/Dashboard', 0755, true);
    file_put_contents($basePath . '/vendor/marko/admin-panel/resources/js/pages/Dashboard/Index.vue', 'vendor');

    $resolver = makeInertiaResolver($basePath);

    expect($resolver->resolve('admin-panel::Dashboard/Index'))
        ->toBe($basePath . '/vendor/marko/admin-panel/resources/js/pages/Dashboard/Index.vue');
});

it('treats app and root prefixes as the project base path', function (): void {
    $basePath = sys_get_temp_dir() . '/marko-inertia-pages-' . uniqid();
    mkdir($basePath . '/resources/js/pages/Dashboard', 0755, true);
    file_put_contents($basePath . '/resources/js/pages/Dashboard/Index.tsx', 'root');

    $resolver = makeInertiaResolver($basePath);

    expect($resolver->resolve('app::Dashboard/Index'))
        ->toBe($basePath . '/resources/js/pages/Dashboard/Index.tsx')
        ->and($resolver->resolve('root::Dashboard/Index'))
        ->toBe($basePath . '/resources/js/pages/Dashboard/Index.tsx');
});

it('throws a helpful exception when a component cannot be found', function (): void {
    $basePath = sys_get_temp_dir() . '/marko-inertia-pages-' . uniqid();
    mkdir($basePath, 0755, true);

    $resolver = makeInertiaResolver($basePath);

    try {
        $resolver->resolve('Missing/Page');
        $this->fail('Expected ComponentNotFoundException was not thrown');
    } catch (ComponentNotFoundException $e) {
        expect($e->getMessage())->toContain("Inertia component 'Missing/Page' not found.")
            ->and($e->getContext())->toContain($basePath . '/resources/js/pages/Missing/Page.tsx')
            ->and($e->getSuggestion())->toContain('Verify the component name');
    }
});
