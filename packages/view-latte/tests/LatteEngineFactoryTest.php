<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\ViewConfig;

describe('LatteEngineFactory', function (): void {
    test('creates Latte Engine', function (): void {
        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn('/tmp/latte');
        $viewConfig->method('autoRefresh')->willReturn(true);
        $viewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig);
        $engine = $factory->create();

        expect($engine)->toBeInstanceOf(Engine::class);
    });

    test('configures cache directory', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);
        $viewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig);
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
        $cacheDir = sys_get_temp_dir() . '/latte-refresh-' . uniqid();
        mkdir($cacheDir, 0755, true);

        // Test with auto refresh enabled
        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);
        $viewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig);
        $engine = $factory->create();

        // Use reflection to access the cache property and check autoRefresh
        $reflection = new ReflectionClass($engine);
        $cacheProperty = $reflection->getProperty('cache');
        $cache = $cacheProperty->getValue($engine);
        expect($cache->autoRefresh)->toBeTrue();

        // Test with auto refresh disabled
        $viewConfig2 = $this->createMock(ViewConfig::class);
        $viewConfig2->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig2->method('autoRefresh')->willReturn(false);
        $viewConfig2->method('strictTypes')->willReturn(true);

        $factory2 = new LatteEngineFactory($viewConfig2);
        $engine2 = $factory2->create();

        $cache2 = $cacheProperty->getValue($engine2);
        expect($cache2->autoRefresh)->toBeFalse();

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        @rmdir($cacheDir);
    });

    test('configures strict types', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-strict-' . uniqid();
        mkdir($cacheDir, 0755, true);

        // Test with strict types enabled
        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);
        $viewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig);
        $engine = $factory->create();

        // Use reflection to check strictTypes property
        $reflection = new ReflectionClass($engine);
        $strictTypesProperty = $reflection->getProperty('strictTypes');
        expect($strictTypesProperty->getValue($engine))->toBeTrue();

        // Test with strict types disabled
        $viewConfig2 = $this->createMock(ViewConfig::class);
        $viewConfig2->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig2->method('autoRefresh')->willReturn(true);
        $viewConfig2->method('strictTypes')->willReturn(false);

        $factory2 = new LatteEngineFactory($viewConfig2);
        $engine2 = $factory2->create();

        expect($strictTypesProperty->getValue($engine2))->toBeFalse();

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        @rmdir($cacheDir);
    });
});
