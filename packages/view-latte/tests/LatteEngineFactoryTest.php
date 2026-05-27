<?php

declare(strict_types=1);

use Latte\Engine;
use Latte\Feature;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\ViewConfig;

describe('LatteEngineFactory', function (): void {
    test('LatteEngineFactory takes both ViewConfig and LatteViewConfig in its constructor', function (): void {
        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn('/tmp/latte');
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createMock(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig, $latteViewConfig);
        $engine = $factory->create();

        expect($engine)->toBeInstanceOf(Engine::class);
    });

    test('creates Latte Engine', function (): void {
        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn('/tmp/latte');
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createMock(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig, $latteViewConfig);
        $engine = $factory->create();

        expect($engine)->toBeInstanceOf(Engine::class);
    });

    test('LatteEngineFactory sets Latte strict types from LatteViewConfig', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-strict-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        // Test with strict types enabled
        $latteViewConfigTrue = $this->createMock(LatteViewConfig::class);
        $latteViewConfigTrue->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig, $latteViewConfigTrue);
        $engine = $factory->create();

        // Test with strict types disabled
        $latteViewConfigFalse = $this->createMock(LatteViewConfig::class);
        $latteViewConfigFalse->method('strictTypes')->willReturn(false);

        $factory2 = new LatteEngineFactory($viewConfig, $latteViewConfigFalse);
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

        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createMock(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig, $latteViewConfig);
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

        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createMock(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig, $latteViewConfig);
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
        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);

        $latteViewConfig = $this->createMock(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $factory = new LatteEngineFactory($viewConfig, $latteViewConfig);
        $engine = $factory->create();

        // Use reflection to access the cache property and check autoRefresh
        $reflection = new ReflectionClass($engine);
        $cacheProperty = $reflection->getProperty('cache');
        $cache = $cacheProperty->getValue($engine);

        // Test with auto refresh disabled
        $viewConfig2 = $this->createMock(ViewConfig::class);
        $viewConfig2->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig2->method('autoRefresh')->willReturn(false);

        $latteViewConfig2 = $this->createMock(LatteViewConfig::class);
        $latteViewConfig2->method('strictTypes')->willReturn(true);

        $factory2 = new LatteEngineFactory($viewConfig2, $latteViewConfig2);
        $engine2 = $factory2->create();

        $cache2 = $cacheProperty->getValue($engine2);
        expect($cache->autoRefresh)->toBeTrue()
            ->and($cache2->autoRefresh)->toBeFalse();

        // Cleanup
        array_map('unlink', glob($cacheDir . '/*'));
        @rmdir($cacheDir);
    });
});
