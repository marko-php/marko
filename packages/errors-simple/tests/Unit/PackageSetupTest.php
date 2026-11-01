<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Error\BootstrapErrorHandler;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Marko\ErrorsSimple\SimpleErrorHandler;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

describe('Package Setup', function () {
    $composerJsonPath = dirname(__DIR__, 2) . '/composer.json';
    $composerJson = json_decode(file_get_contents($composerJsonPath), true);
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    it('has valid composer.json with name marko/errors-simple', function () use ($composerJson) {
        expect($composerJson['name'])->toBe('marko/errors-simple');
    });

    it('requires php 8.5 or higher', function () use ($composerJson) {
        expect($composerJson['require']['php'])->toMatch('/\^?>=?8\.5/');
    });

    it('requires marko/core', function () use ($composerJson) {
        expect($composerJson['require'])->toHaveKey('marko/core');
    });

    it('requires marko/errors', function () use ($composerJson) {
        expect($composerJson['require'])->toHaveKey('marko/errors');
    });

    it('has no other dependencies', function () use ($composerJson) {
        $expectedDeps = ['php', 'marko/core', 'marko/clock', 'marko/errors'];
        $actualDeps = array_keys($composerJson['require']);
        sort($expectedDeps);
        sort($actualDeps);
        expect($actualDeps)->toBe($expectedDeps);
    });

    it('has PSR-4 autoloading for Marko\\ErrorsSimple namespace', function () use ($composerJson) {
        expect($composerJson['autoload']['psr-4'])->toHaveKey('Marko\\ErrorsSimple\\');
        expect($composerJson['autoload']['psr-4']['Marko\\ErrorsSimple\\'])->toBe('src/');
    });

    it('binds SimpleErrorHandler to ErrorHandlerInterface in module.php', function () use ($modulePath) {
        $module = require $modulePath;
        expect($module['bindings'])->toHaveKey(ErrorHandlerInterface::class);
        expect($module['bindings'][ErrorHandlerInterface::class])->toBe(SimpleErrorHandler::class);
    });

    it('auto-registers error handler via module boot hook', function () use ($modulePath) {
        $module = require $modulePath;
        expect($module)->toHaveKey('boot');
        expect($module['boot'])->toBeCallable();
    });

    it('replaces the core bootstrap error handler instead of stacking on top of it', function () use ($modulePath) {
        $module = require $modulePath;
        $peek = static function (): mixed {
            $current = set_exception_handler(null);
            restore_exception_handler();

            return $current;
        };
        $original = $peek();
        $moduleHandler = static function (Throwable $throwable): void {};
        $errorHandler = test()->createStub(ErrorHandlerInterface::class);
        $errorHandler->method('register')->willReturnCallback(
            static function () use ($moduleHandler): void {
                set_exception_handler($moduleHandler);
            },
        );
        $container = new Container(new PreferenceRegistry());
        $container->instance(ContainerInterface::class, $container);
        $container->instance(ErrorHandlerInterface::class, $errorHandler);
        $bootstrapErrorHandler = new BootstrapErrorHandler(new AppEnvironment(['APP_ENV' => 'local']));
        $container->instance(BootstrapErrorHandler::class, $bootstrapErrorHandler);

        $bootstrapErrorHandler->register();
        $container->call($module['boot']);
        $active = $peek();
        restore_exception_handler();
        $underneath = $peek();

        expect($active)->toBe($moduleHandler)
            ->and($underneath)->toBe($original)
            ->and($bootstrapErrorHandler->isActive())->toBeFalse();
    });

    it('removes the handlers its boot callback registered from its shutdown callback', function () use ($modulePath) {
        $module = require $modulePath;
        $stacks = static function (): array {
            $read = static function (Closure $set, Closure $restore): array {
                $handlers = [];

                while (($handler = $set(null)) !== null) {
                    $restore();
                    $handlers[] = $handler;
                    $restore();
                }

                $restore();
                $handlers = array_reverse($handlers);

                foreach ($handlers as $handler) {
                    $set($handler);
                }

                return $handlers;
            };

            return [
                $read(set_error_handler(...), restore_error_handler(...)),
                $read(set_exception_handler(...), restore_exception_handler(...)),
            ];
        };
        $container = new Container(new PreferenceRegistry());
        $container->instance(ContainerInterface::class, $container);
        $container->instance(ClockInterface::class, new FakeClock());
        $container->instance(BootstrapErrorHandler::class, new BootstrapErrorHandler());

        foreach ($module['bindings'] as $abstract => $concrete) {
            $container->bind($abstract, $concrete);
        }

        foreach ($module['singletons'] ?? [] as $abstract) {
            $container->singleton($abstract);
        }

        $before = $stacks();
        $container->call($module['boot']);
        $booted = $stacks();
        $container->call($module['shutdown']);

        expect($booted)->not->toBe($before)
            ->and($stacks())->toBe($before);
    });

    it('exports SimpleErrorHandler', function () {
        expect(class_exists(SimpleErrorHandler::class))->toBeTrue();
    });

    it('exports TextFormatter', function () {
        expect(class_exists(TextFormatter::class))->toBeTrue();
    });

    it('exports BasicHtmlFormatter', function () {
        expect(class_exists(BasicHtmlFormatter::class))->toBeTrue();
    });

    it('exports CodeSnippetExtractor', function () {
        expect(class_exists(CodeSnippetExtractor::class))->toBeTrue();
    });

    it('exports Environment', function () {
        expect(class_exists(Environment::class))->toBeTrue();
    });
});
