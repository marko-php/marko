<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\Exceptions\InvalidTemplateException;
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
    $tempDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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
    $tempDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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
    $vendorDir = sys_get_temp_dir() . '/marko-test-vendor-' . bin2hex(random_bytes(8));
    $modulesDir = sys_get_temp_dir() . '/marko-test-modules-' . bin2hex(random_bytes(8));
    $appDir = sys_get_temp_dir() . '/marko-test-app-' . bin2hex(random_bytes(8));

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
    $vendorDir = sys_get_temp_dir() . '/marko-test-vendor-' . bin2hex(random_bytes(8));
    $appDir = sys_get_temp_dir() . '/marko-test-app-' . bin2hex(random_bytes(8));

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
    $tempDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
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
    $tempDir1 = sys_get_temp_dir() . '/marko-test-app-' . bin2hex(random_bytes(8));
    $tempDir2 = sys_get_temp_dir() . '/marko-test-vendor-' . bin2hex(random_bytes(8));
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
    $tempDir1 = sys_get_temp_dir() . '/marko-test-1-' . bin2hex(random_bytes(8));
    $tempDir2 = sys_get_temp_dir() . '/marko-test-2-' . bin2hex(random_bytes(8));
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

it('it matches templates_for by basename (marko/admin-panel matches admin-panel::path)', function (): void {
    $siblingDir = sys_get_temp_dir() . '/marko-test-sibling-' . bin2hex(random_bytes(8));
    mkdir($siblingDir . '/resources/views/dashboard', 0755, true);
    file_put_contents($siblingDir . '/resources/views/dashboard/index.latte', 'sibling content');

    $modules = [
        new ModuleManifest(
            name: 'marko/admin-panel-latte',
            version: '1.0.0',
            path: $siblingDir,
            source: 'vendor',
            // Full package name "marko/admin-panel" should match short name "admin-panel"
            extra: ['marko' => ['templates_for' => 'marko/admin-panel']],
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $paths = $resolver->getSearchedPaths('admin-panel::dashboard/index');

    expect($paths)->toHaveCount(1)
        ->and($paths[0])->toBe($siblingDir . '/resources/views/dashboard/index.latte');

    // Cleanup
    unlink($siblingDir . '/resources/views/dashboard/index.latte');
    rmdir($siblingDir . '/resources/views/dashboard');
    rmdir($siblingDir . '/resources/views');
    rmdir($siblingDir . '/resources');
    rmdir($siblingDir);
});

it('it does not throw when templates_for is present but not a string', function (): void {
    $dir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    mkdir($dir, 0755, true);

    $modules = [
        new ModuleManifest(
            name: 'marko/some-module',
            version: '1.0.0',
            path: $dir,
            source: 'vendor',
            extra: ['marko' => ['templates_for' => ['not', 'a', 'string']]],
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $paths = $resolver->getSearchedPaths('admin-panel::dashboard/index');

    expect($paths)->toBeEmpty();

    rmdir($dir);
});

it('it does not throw when a module\'s extra.marko key is missing entirely', function (): void {
    $dir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    mkdir($dir, 0755, true);

    $modules = [
        new ModuleManifest(
            name: 'marko/some-module',
            version: '1.0.0',
            path: $dir,
            source: 'vendor',
            extra: [],  // extra exists but marko key is missing
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $paths = $resolver->getSearchedPaths('admin-panel::dashboard/index');

    expect($paths)->toBeEmpty();

    rmdir($dir);
});

it('it ignores modules without a templates_for declaration when looking for sibling providers', function (): void {
    $irrelevantDir = sys_get_temp_dir() . '/marko-test-irrelevant-' . bin2hex(random_bytes(8));
    mkdir($irrelevantDir, 0755, true);

    $modules = [
        new ModuleManifest(
            name: 'marko/other-module',
            version: '1.0.0',
            path: $irrelevantDir,
            source: 'vendor',
            // No extra / templates_for declared
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $paths = $resolver->getSearchedPaths('admin-panel::dashboard/index');

    expect($paths)->toBeEmpty();

    rmdir($irrelevantDir);
});

it('it puts the parent path before the sibling path in the search order', function (): void {
    $parentDir = sys_get_temp_dir() . '/marko-test-parent-' . bin2hex(random_bytes(8));
    $siblingDir = sys_get_temp_dir() . '/marko-test-sibling-' . bin2hex(random_bytes(8));
    mkdir($parentDir . '/resources/views/dashboard', 0755, true);
    mkdir($siblingDir . '/resources/views/dashboard', 0755, true);
    file_put_contents($parentDir . '/resources/views/dashboard/index.latte', 'parent');
    file_put_contents($siblingDir . '/resources/views/dashboard/index.latte', 'sibling');

    // Put sibling first in the module list to ensure ordering is by match type, not list order
    $modules = [
        new ModuleManifest(
            name: 'marko/admin-panel-latte',
            version: '1.0.0',
            path: $siblingDir,
            source: 'vendor',
            extra: ['marko' => ['templates_for' => 'marko/admin-panel']],
        ),
        new ModuleManifest(
            name: 'marko/admin-panel',
            version: '1.0.0',
            path: $parentDir,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    // resolve() should return the parent (since parent path comes first in search)
    $result = $resolver->resolve('admin-panel::dashboard/index');

    expect($result)->toBe($parentDir . '/resources/views/dashboard/index.latte');

    // Cleanup
    unlink($parentDir . '/resources/views/dashboard/index.latte');
    unlink($siblingDir . '/resources/views/dashboard/index.latte');
    rmdir($parentDir . '/resources/views/dashboard');
    rmdir($siblingDir . '/resources/views/dashboard');
    rmdir($parentDir . '/resources/views');
    rmdir($siblingDir . '/resources/views');
    rmdir($parentDir . '/resources');
    rmdir($siblingDir . '/resources');
    rmdir($parentDir);
    rmdir($siblingDir);
});

it('it includes both the parent and the sibling paths in getSearchedPaths when both exist', function (): void {
    $parentDir = sys_get_temp_dir() . '/marko-test-parent-' . bin2hex(random_bytes(8));
    $siblingDir = sys_get_temp_dir() . '/marko-test-sibling-' . bin2hex(random_bytes(8));
    mkdir($parentDir, 0755, true);
    mkdir($siblingDir, 0755, true);

    $modules = [
        new ModuleManifest(
            name: 'marko/admin-panel',
            version: '1.0.0',
            path: $parentDir,
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/admin-panel-latte',
            version: '1.0.0',
            path: $siblingDir,
            source: 'vendor',
            extra: ['marko' => ['templates_for' => 'marko/admin-panel']],
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $paths = $resolver->getSearchedPaths('admin-panel::dashboard/index');

    expect($paths)->toHaveCount(2)
        ->and($paths[0])->toBe($parentDir . '/resources/views/dashboard/index.latte')
        ->and($paths[1])->toBe($siblingDir . '/resources/views/dashboard/index.latte');

    // Cleanup
    rmdir($parentDir);
    rmdir($siblingDir);
});

it('resolves a template via a templates_for declaration on a sibling module', function (): void {
    $siblingDir = sys_get_temp_dir() . '/marko-test-sibling-' . bin2hex(random_bytes(8));
    mkdir($siblingDir . '/resources/views/dashboard', 0755, true);
    file_put_contents($siblingDir . '/resources/views/dashboard/index.latte', 'sibling content');

    $modules = [
        new ModuleManifest(
            name: 'marko/admin-panel-latte',
            version: '1.0.0',
            path: $siblingDir,
            source: 'vendor',
            extra: ['marko' => ['templates_for' => 'marko/admin-panel']],
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $result = $resolver->resolve('admin-panel::dashboard/index');

    expect($result)->toBe($siblingDir . '/resources/views/dashboard/index.latte');

    // Cleanup
    unlink($siblingDir . '/resources/views/dashboard/index.latte');
    rmdir($siblingDir . '/resources/views/dashboard');
    rmdir($siblingDir . '/resources/views');
    rmdir($siblingDir . '/resources');
    rmdir($siblingDir);
});

it('resolves a template via the parent module name match (existing behavior preserved)', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/resources/views/dashboard', 0755, true);
    file_put_contents($tempDir . '/resources/views/dashboard/index.latte', 'parent content');

    $modules = [
        new ModuleManifest(
            name: 'marko/admin-panel',
            version: '1.0.0',
            path: $tempDir,
            source: 'vendor',
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        createTestViewConfig(),
    );

    $result = $resolver->resolve('admin-panel::dashboard/index');

    expect($result)->toBe($tempDir . '/resources/views/dashboard/index.latte');

    // Cleanup
    unlink($tempDir . '/resources/views/dashboard/index.latte');
    rmdir($tempDir . '/resources/views/dashboard');
    rmdir($tempDir . '/resources/views');
    rmdir($tempDir . '/resources');
    rmdir($tempDir);
});

it('uses FakeConfigRepository in ModuleTemplateResolverTest', function (): void {
    $repo = new FakeConfigRepository(['view.extension' => '.latte']);
    $viewConfig = new ViewConfig($repo);

    expect($viewConfig->extension())->toBe('.latte');
});

describe('template name validation', function (): void {
    beforeEach(function (): void {
        $this->moduleDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
        $this->outsideDir = sys_get_temp_dir() . '/marko-test-outside-' . bin2hex(random_bytes(8));
        mkdir($this->moduleDir . '/resources/views/post', 0755, true);
        mkdir($this->outsideDir, 0755, true);
        file_put_contents($this->moduleDir . '/resources/views/post/show.latte', 'inside');
        file_put_contents($this->outsideDir . '/evil.latte', '{php system("id")}');

        $this->resolver = new ModuleTemplateResolver(
            new ModuleRepository([
                new ModuleManifest(
                    name: 'vendor/blog',
                    version: '1.0.0',
                    path: $this->moduleDir,
                    source: 'vendor',
                ),
            ]),
            createTestViewConfig(),
        );
    });

    afterEach(function (): void {
        foreach ([$this->moduleDir, $this->outsideDir] as $dir) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($items as $item) {
                if (
                    $item->isDir()
                    && !$item->isLink()
                ) {
                    rmdir($item->getPathname());
                } else {
                    unlink($item->getPathname());
                }
            }

            rmdir($dir);
        }
    });

    it('rejects a module-prefixed template name that traverses out with ../', function (): void {
        $relative = '../../../' . basename($this->outsideDir) . '/evil';

        // Without validation this would resolve to the planted evil.latte outside the module
        expect(file_exists($this->moduleDir . '/resources/views/' . $relative . '.latte'))->toBeTrue()
            ->and(fn () => $this->resolver->resolve('blog::' . $relative))
            ->toThrow(InvalidTemplateException::class, "Invalid template name 'blog::$relative'");
    });

    it('rejects an unprefixed template name that traverses out with ../', function (): void {
        expect(fn () => $this->resolver->resolve('post/../../../../etc/passwd'))
            ->toThrow(InvalidTemplateException::class, "contains a '..' path segment");
    });

    it('rejects traversal in getSearchedPaths too', function (): void {
        expect(fn () => $this->resolver->getSearchedPaths('blog::../secrets'))
            ->toThrow(InvalidTemplateException::class);
    });

    it('rejects absolute template paths', function (): void {
        expect(fn () => $this->resolver->resolve('blog::' . $this->outsideDir . '/evil'))
            ->toThrow(InvalidTemplateException::class, 'absolute paths are not allowed')
            ->and(fn () => $this->resolver->resolve('/etc/passwd'))
            ->toThrow(InvalidTemplateException::class, 'absolute paths are not allowed');
    });

    it('rejects template names containing a NUL byte', function (): void {
        expect(fn () => $this->resolver->resolve("blog::post/show\0.php"))
            ->toThrow(InvalidTemplateException::class, 'contains a NUL byte');
    });

    it('rejects template names containing a backslash', function (): void {
        expect(fn () => $this->resolver->resolve('blog::..\\..\\evil'))
            ->toThrow(InvalidTemplateException::class, 'contains a backslash');
    });

    it('rejects template names with characters outside the allowed set', function (): void {
        expect(fn () => $this->resolver->resolve('blog::post/show?x=1'))
            ->toThrow(InvalidTemplateException::class, "the path segment 'show?x=1' contains characters")
            ->and(fn () => $this->resolver->resolve('blog::post::show'))
            ->toThrow(InvalidTemplateException::class)
            ->and(fn () => $this->resolver->resolve('bl og::post/show'))
            ->toThrow(InvalidTemplateException::class, "the module name 'bl og'");
    });

    it('rejects empty module names, empty paths, and empty or dot segments', function (): void {
        expect(fn () => $this->resolver->resolve('::post/show'))
            ->toThrow(InvalidTemplateException::class, "the module name ''")
            ->and(fn () => $this->resolver->resolve('blog::'))
            ->toThrow(InvalidTemplateException::class, 'the template path is empty')
            ->and(fn () => $this->resolver->resolve('blog::post//show'))
            ->toThrow(InvalidTemplateException::class, 'empty path segment')
            ->and(fn () => $this->resolver->resolve('blog::post/./show'))
            ->toThrow(InvalidTemplateException::class, "contains a '.' path segment");
    });

    it('rejects a template whose file is a symlink escaping the views directory', function (): void {
        symlink($this->outsideDir . '/evil.latte', $this->moduleDir . '/resources/views/post/evil.latte');

        expect(fn () => $this->resolver->resolve('blog::post/evil'))
            ->toThrow(
                InvalidTemplateException::class,
                "Template 'blog::post/evil' resolves outside its module's views directory",
            );
    });

    it('rejects a template reached through a symlinked directory escaping the views directory', function (): void {
        symlink($this->outsideDir, $this->moduleDir . '/resources/views/linked');

        expect(fn () => $this->resolver->resolve('blog::linked/evil'))
            ->toThrow(InvalidTemplateException::class, 'resolves outside');
    });

    it('allows a symlink that stays inside the views directory', function (): void {
        symlink(
            $this->moduleDir . '/resources/views/post/show.latte',
            $this->moduleDir . '/resources/views/post/alias.latte',
        );

        expect($this->resolver->resolve('blog::post/alias'))
            ->toBe($this->moduleDir . '/resources/views/post/alias.latte');
    });

    it('resolves normal names with hyphens, underscores, dots and full module names', function (): void {
        mkdir($this->moduleDir . '/resources/views/admin-area/sub_dir', 0755, true);
        file_put_contents($this->moduleDir . '/resources/views/admin-area/sub_dir/email.html.latte', 'ok');

        expect($this->resolver->resolve('blog::post/show'))
            ->toBe($this->moduleDir . '/resources/views/post/show.latte')
            ->and($this->resolver->resolve('vendor/blog::post/show'))
            ->toBe($this->moduleDir . '/resources/views/post/show.latte')
            ->and($this->resolver->resolve('post/show'))
            ->toBe($this->moduleDir . '/resources/views/post/show.latte')
            ->and($this->resolver->resolve('blog::admin-area/sub_dir/email.html'))
            ->toBe($this->moduleDir . '/resources/views/admin-area/sub_dir/email.html.latte');
    });
});
