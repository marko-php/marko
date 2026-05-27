<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Core\Container\ContainerInterface;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ViewInterface;

describe('view-latte module.php', function (): void {
    test('it has a bindings array', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray();
    });

    test('it binds ViewInterface to LatteView as a simple class mapping', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'])->toHaveKey(ViewInterface::class)
            ->and($module['bindings'][ViewInterface::class])->toBe(LatteView::class);
    });

    test('it binds Latte Engine via a closure that calls LatteEngineFactory', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'])->toHaveKey(Engine::class)
            ->and($module['bindings'][Engine::class])->toBeInstanceOf(Closure::class);
    });

    test('the Engine closure resolves the engine via LatteEngineFactory::create()', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        $engine = $this->createMock(Engine::class);

        $engineFactory = $this->createMock(LatteEngineFactory::class);
        $engineFactory->expects($this->once())
            ->method('create')
            ->willReturn($engine);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn (string $class) => match ($class) {
                LatteEngineFactory::class => $engineFactory,
                default => throw new Exception("Unexpected class: $class"),
            });

        $closure = $module['bindings'][Engine::class];
        $result = $closure($container);

        expect($result)->toBe($engine);
    });
});
