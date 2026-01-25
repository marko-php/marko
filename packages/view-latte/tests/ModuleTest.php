<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Core\Container\ContainerInterface;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewInterface;

describe('view-latte module.php', function (): void {
    test('module.php exists with correct structure', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray();
    });

    test('module.php binds ViewInterface via factory', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'])->toHaveKey(ViewInterface::class)
            ->and($module['bindings'][ViewInterface::class])->toBeInstanceOf(Closure::class);
    });

    test('module.php uses LatteEngineFactory', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        // Mock the engine from factory
        $engine = $this->createMock(Engine::class);

        // Mock the LatteEngineFactory
        $engineFactory = $this->createMock(LatteEngineFactory::class);
        $engineFactory->expects($this->once())
            ->method('create')
            ->willReturn($engine);

        // Mock the TemplateResolverInterface
        $resolver = $this->createMock(TemplateResolverInterface::class);

        // Mock the container
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function (string $class) use ($engineFactory, $resolver) {
                return match ($class) {
                    LatteEngineFactory::class => $engineFactory,
                    TemplateResolverInterface::class => $resolver,
                    default => throw new Exception("Unexpected class: $class"),
                };
            });

        // Call the factory closure
        $factory = $module['bindings'][ViewInterface::class];
        $view = $factory($container);

        expect($view)->toBeInstanceOf(LatteView::class);
    });
});
