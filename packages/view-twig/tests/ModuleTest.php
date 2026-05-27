<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigView;
use Marko\View\ViewInterface;
use Twig\Environment;

describe('view-twig module.php', function (): void {
    test('it has a bindings array', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray();
    });

    test('it binds ViewInterface to TwigView as a simple class mapping', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'])->toHaveKey(ViewInterface::class)
            ->and($module['bindings'][ViewInterface::class])->toBe(TwigView::class);
    });

    test('it binds Twig Environment via a closure that calls TwigEngineFactory', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'])->toHaveKey(Environment::class)
            ->and($module['bindings'][Environment::class])->toBeInstanceOf(Closure::class);
    });

    test('the Environment closure resolves the engine via TwigEngineFactory::create()', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        $engine = $this->createMock(Environment::class);

        $engineFactory = $this->createMock(TwigEngineFactory::class);
        $engineFactory->expects($this->once())
            ->method('create')
            ->willReturn($engine);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn (string $class) => match ($class) {
                TwigEngineFactory::class => $engineFactory,
                default => throw new Exception("Unexpected class: $class"),
            });

        $closure = $module['bindings'][Environment::class];
        $result = $closure($container);

        expect($result)->toBe($engine);
    });
});
