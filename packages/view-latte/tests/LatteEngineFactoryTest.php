<?php

declare(strict_types=1);

use Latte\Engine;
use Latte\Feature;
use Marko\Core\Path\ProjectPaths;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\Latte\Tests\Fixtures\ViewProviders;
use Marko\View\ViewConfig;

describe('LatteEngineFactory', function (): void {
    test('LatteEngineFactory takes both ViewConfig and LatteViewConfig in its constructor', function (): void {
        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn('/tmp/latte');
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createStub(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfig,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine = $factory->create();

        expect($engine)->toBeInstanceOf(Engine::class);
    });

    test('creates Latte Engine', function (): void {
        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn('/tmp/latte');
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createStub(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfig,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine = $factory->create();

        expect($engine)->toBeInstanceOf(Engine::class);
    });

    test('LatteEngineFactory sets Latte strict types from LatteViewConfig', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-strict-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        // Test with strict types enabled
        $latteViewConfigTrue = $this->createStub(LatteViewConfig::class);
        $latteViewConfigTrue->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfigTrue,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine = $factory->create();

        // Test with strict types disabled
        $latteViewConfigFalse = $this->createStub(LatteViewConfig::class);
        $latteViewConfigFalse->method('strictTypes')->willReturn(false);

        $factory2 = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfigFalse,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine2 = $factory2->create();

        expect($engine->hasFeature(Feature::StrictTypes))->toBeTrue()
            ->and($engine2->hasFeature(Feature::StrictTypes))->toBeFalse();

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        @rmdir($cacheDir);
    });

    test('LatteEngineFactory continues to set cache directory and auto refresh from ViewConfig', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-test-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createStub(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfig,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine = $factory->create();

        // Verify by rendering a simple template - it should create cache files
        $templatePath = $cacheDir . '/test.latte';
        file_put_contents($templatePath, 'Hello {$name}');

        $engine->renderToString($templatePath, ['name' => 'World']);

        // Check that cache files were created in the configured directory
        $cacheFiles = glob($cacheDir . '/*');
        expect(count($cacheFiles))->toBeGreaterThan(1); // template + cache file(s)

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('configures cache directory', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-test-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createStub(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfig,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine = $factory->create();

        // Verify by rendering a simple template - it should create cache files
        $templatePath = $cacheDir . '/test.latte';
        file_put_contents($templatePath, 'Hello {$name}');

        $engine->renderToString($templatePath, ['name' => 'World']);

        // Check that cache files were created in the configured directory
        $cacheFiles = glob($cacheDir . '/*');
        expect(count($cacheFiles))->toBeGreaterThan(1); // template + cache file(s)

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('configures auto refresh', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-refresh-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        // Test with auto refresh enabled
        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createStub(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfig,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine = $factory->create();

        // Use reflection to access the cache property and check autoRefresh
        $reflection = new ReflectionClass($engine);
        $cacheProperty = $reflection->getProperty('cache');
        $cache = $cacheProperty->getValue($engine);

        // Test with auto refresh disabled
        $viewConfig2 = $this->createStub(ViewConfig::class);
        $viewConfig2->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig2->method('autoRefresh')->willReturn(false);

        $latteViewConfig2 = $this->createStub(LatteViewConfig::class);
        $latteViewConfig2->method('strictTypes')->willReturn(true);

        $factory2 = new LatteEngineFactory(
            $viewConfig2,
            $latteViewConfig2,
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        );
        $engine2 = $factory2->create();

        $cache2 = $cacheProperty->getValue($engine2);
        expect($cache->autoRefresh)->toBeTrue()
            ->and($cache2->autoRefresh)->toBeFalse();

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        @rmdir($cacheDir);
    });
});

describe('LatteEngineFactory cache directory hardening', function (): void {
    beforeEach(function (): void {
        $this->base = sys_get_temp_dir() . '/marko-latte-base-' . bin2hex(random_bytes(8));
        mkdir($this->base, 0o700);
    });

    afterEach(function (): void {
        foreach (array_reverse(glob($this->base . '/{,*/,*/*/}*', GLOB_BRACE | GLOB_MARK)) as $path) {
            str_ends_with($path, '/') ? rmdir($path) : unlink($path);
        }

        rmdir($this->base);
    });

    test('it resolves the default storage/views cache directory under the project base path', function (): void {
        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn('storage/views');
        $viewConfig->method('autoRefresh')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $this->createStub(LatteViewConfig::class),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths($this->base)),
        );

        expect($factory->create()->getCacheFile('main'))->toStartWith($this->base . '/storage/views/')
            ->and(fileperms($this->base . '/storage/views') & 0o777)->toBe(0o700);
    });

    test('it refuses a world-writable cache directory', function (): void {
        $shared = $this->base . '/views';
        mkdir($shared);
        chmod($shared, 0o777);

        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($shared);
        $viewConfig->method('autoRefresh')->willReturn(true);

        $factory = new LatteEngineFactory(
            $viewConfig,
            $this->createStub(LatteViewConfig::class),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths($this->base)),
        );

        expect(fn (): Engine => $factory->create())
            ->toThrow(InsecureCacheDirectoryException::class, 'world-writable');
    });
});
