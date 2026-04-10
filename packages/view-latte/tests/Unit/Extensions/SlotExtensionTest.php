<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\View\Latte\Extensions\SlotExtension;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\ViewConfig;

describe('SlotExtension', function (): void {
    test('it registers the slot tag with the Latte engine', function (): void {
        $extension = new SlotExtension();
        $tags = $extension->getTags();

        expect($tags)->toHaveKey('slot');
    });

    test('it outputs empty string when slot has no content', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-slot-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);
        $engine->addExtension(new SlotExtension());

        $templatePath = $cacheDir . '/slot-empty.latte';
        file_put_contents($templatePath, '{slot sidebar}{/slot}');

        $html = $engine->renderToString($templatePath, [
            'slots' => [],
        ]);

        expect($html)->toBe('');

        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('it outputs pre-rendered HTML for a named slot', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-slot-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);
        $engine->addExtension(new SlotExtension());

        $templatePath = $cacheDir . '/slot-test.latte';
        file_put_contents($templatePath, '{slot content}{/slot}');

        $html = $engine->renderToString($templatePath, [
            'slots' => ['content' => '<h1>Hello World</h1>'],
        ]);

        expect($html)->toBe('<h1>Hello World</h1>');

        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('it handles nested slot names with dot-notation', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-slot-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);
        $engine->addExtension(new SlotExtension());

        $templatePath = $cacheDir . '/dot-slot.latte';
        file_put_contents($templatePath, '{slot header.title}{/slot}');

        $html = $engine->renderToString($templatePath, [
            'slots' => ['header.title' => '<h1>Nested Title</h1>'],
        ]);

        expect($html)->toBe('<h1>Nested Title</h1>');

        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('it works with multiple slots in the same template', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-slot-test-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);
        $engine->addExtension(new SlotExtension());

        $templatePath = $cacheDir . '/multi-slot.latte';
        file_put_contents($templatePath, '<header>{slot header}{/slot}</header><main>{slot content}{/slot}</main><footer>{slot footer}{/slot}</footer>');

        $html = $engine->renderToString($templatePath, [
            'slots' => [
                'header' => '<h1>Title</h1>',
                'content' => '<p>Body</p>',
                'footer' => '<p>Footer</p>',
            ],
        ]);

        expect($html)->toBe('<header><h1>Title</h1></header><main><p>Body</p></main><footer><p>Footer</p></footer>');

        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });

    test('it integrates with LatteEngineFactory for automatic registration', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-slot-factory-' . uniqid();
        mkdir($cacheDir, 0755, true);

        $viewConfig = $this->createMock(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);
        $viewConfig->method('strictTypes')->willReturn(false);

        $factory = new LatteEngineFactory($viewConfig);
        $engine = $factory->create();

        $templatePath = $cacheDir . '/factory-slot.latte';
        file_put_contents($templatePath, '{slot content}{/slot}');

        $html = $engine->renderToString($templatePath, [
            'slots' => ['content' => '<p>Factory Slot</p>'],
        ]);

        expect($html)->toBe('<p>Factory Slot</p>');

        array_map('unlink', glob($cacheDir . '/*'));
        rmdir($cacheDir);
    });
});
