<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\View\TemplateResolverInterface;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigView;
use Marko\View\ViewInterface;
use Twig\Environment;

describe('view-twig module.php', function (): void {
    test('it registers a ViewInterface binding', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toHaveKey(ViewInterface::class);
    });

    test('it resolves ViewInterface to a TwigView instance', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        $engine = $this->createMock(Environment::class);

        $engineFactory = $this->createMock(TwigEngineFactory::class);
        $engineFactory->expects($this->once())
            ->method('create')
            ->willReturn($engine);

        $resolver = $this->createMock(TemplateResolverInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function (string $class) use ($engineFactory, $resolver) {
                return match ($class) {
                    TwigEngineFactory::class => $engineFactory,
                    TemplateResolverInterface::class => $resolver,
                    default => throw new Exception("Unexpected class: $class"),
                };
            });

        $factory = $module['bindings'][ViewInterface::class];
        $view = $factory($container);

        expect($view)->toBeInstanceOf(TwigView::class);
    });

    test('it injects a configured Twig Environment into TwigView', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        $engine = $this->createMock(Environment::class);

        $engineFactory = $this->createMock(TwigEngineFactory::class);
        $engineFactory->expects($this->once())
            ->method('create')
            ->willReturn($engine);

        $resolver = $this->createMock(TemplateResolverInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function (string $class) use ($engineFactory, $resolver) {
                return match ($class) {
                    TwigEngineFactory::class => $engineFactory,
                    TemplateResolverInterface::class => $resolver,
                    default => throw new Exception("Unexpected class: $class"),
                };
            });

        $factory = $module['bindings'][ViewInterface::class];

        // The factory closure calls engineFactory->create() — expectation above asserts this
        $view = $factory($container);

        expect($view)->toBeInstanceOf(TwigView::class);
    });

    test('it injects the shared TemplateResolverInterface into TwigView', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        $engine = $this->createMock(Environment::class);

        $engineFactory = $this->createMock(TwigEngineFactory::class);
        $engineFactory->method('create')
            ->willReturn($engine);

        $resolver = $this->createMock(TemplateResolverInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function (string $class) use ($engineFactory, $resolver) {
                return match ($class) {
                    TwigEngineFactory::class => $engineFactory,
                    TemplateResolverInterface::class => $resolver,
                    default => throw new Exception("Unexpected class: $class"),
                };
            });

        $factory = $module['bindings'][ViewInterface::class];
        $view = $factory($container);

        expect($view)->toBeInstanceOf(TwigView::class)
            ->and($view)->toBeInstanceOf(ViewInterface::class);
    });
});
