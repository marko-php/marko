<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Tests\Unit\HttpAware;

use Closure;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Error\BootstrapErrorHandler;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsAdvanced\Tests\Fixtures\OutputSafeAdvancedErrorHandler;
use Marko\ErrorsSimple\Environment;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;
use ReflectionProperty;
use RuntimeException;
use Throwable;

class GoneException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 410;
    }

    public function getHeaders(): array
    {
        return ['X-Gone' => 'yes'];
    }

    public function getResponseData(): array
    {
        return ['message' => 'This resource is gone.'];
    }
}

/**
 * @param array<string, string> $server
 */
function advancedWebEnvironment(
    bool $production,
    array $server = ['HTTP_ACCEPT' => 'text/html'],
): Environment {
    return new Environment(
        sapi: 'fpm-fcgi',
        envVars: ['MARKO_ENV' => $production ? 'production' : 'development'],
        server: $server,
    );
}

/**
 * @return array{0: OutputSafeAdvancedErrorHandler, 1: string}
 */
function handleWithAdvanced(
    Environment $environment,
    Throwable $throwable,
): array {
    $handler = new OutputSafeAdvancedErrorHandler(clock: new FakeClock(), environment: $environment);

    ob_start();
    $handler->handleException($throwable);
    $output = (string) ob_get_clean();

    return [$handler, $output];
}

describe('AdvancedErrorHandler container resolution', function (): void {
    it('replaces the core bootstrap error handler instead of stacking on top of it', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
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

    it('removes the handlers its boot callback registered from its shutdown callback', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
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

    it('resolves ErrorHandlerInterface from the module bindings', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
        $container = new Container(new PreferenceRegistry());
        $container->instance(ClockInterface::class, new FakeClock());

        foreach ($module['bindings'] as $abstract => $concrete) {
            $container->bind($abstract, $concrete);
        }

        expect($container->get(ErrorHandlerInterface::class))->toBeInstanceOf(AdvancedErrorHandler::class);
    });

    it('builds the handler with the bound clock from the module', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
        $container = new Container(new PreferenceRegistry());
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $container->instance(ClockInterface::class, $clock);

        foreach ($module['bindings'] as $abstract => $concrete) {
            $container->bind($abstract, $concrete);
        }

        $handler = $container->get(ErrorHandlerInterface::class);

        expect(new ReflectionProperty(AdvancedErrorHandler::class, 'clock')->getValue($handler))->toBe($clock);
    });

    it('builds the handler from the container AppEnvironment so it agrees with errors-simple', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
        $container = new Container(new PreferenceRegistry());
        $container->instance(ClockInterface::class, new FakeClock());
        $appEnvironment = new AppEnvironment(['APP_ENV' => 'local']);
        $container->instance(AppEnvironment::class, $appEnvironment);

        foreach ($module['bindings'] as $abstract => $concrete) {
            $container->bind($abstract, $concrete);
        }

        $handler = $container->get(ErrorHandlerInterface::class);
        $environment = new ReflectionProperty(AdvancedErrorHandler::class, 'environment')->getValue($handler);

        expect($environment)->toBeInstanceOf(Environment::class)
            ->and($environment->appEnvironment())->toBe($appEnvironment)
            ->and($environment->isDevelopment())->toBeTrue();
    });

    it('renders the generic page when the environment is unset', function (): void {
        $environment = new Environment(
            sapi: 'fpm-fcgi',
            server: ['HTTP_ACCEPT' => 'text/html'],
            appEnvironment: new AppEnvironment([]),
        );

        [, $output] = handleWithAdvanced($environment, new RuntimeException('SQLSTATE secret'));

        expect($output)->toContain('An error occurred')
            ->not->toContain('SQLSTATE');
    });
});

describe('AdvancedErrorHandler in web SAPI', function (): void {
    it('renders the generic page in production without paths, source or trace', function (): void {
        [$handler, $output] = handleWithAdvanced(
            advancedWebEnvironment(production: true),
            new RuntimeException('SQLSTATE[42S02] secret table'),
        );

        expect($handler->statusCodeSet)->toBe(500)
            ->and($output)->toContain('An error occurred')
            ->not->toContain('SQLSTATE')
            ->not->toContain(__FILE__)
            ->not->toContain('HttpAwareAdvancedErrorHandlerTest')
            ->not->toContain('handleWithAdvanced');
    });

    it('sets status 500 in web SAPI', function (): void {
        [$handler] = handleWithAdvanced(advancedWebEnvironment(production: false), new RuntimeException('Boom'));

        expect($handler->statusCodeSet)->toBe(500)
            ->and($handler->headersSent['Content-Type'])->toBe('text/html; charset=UTF-8');
    });

    it('clears output buffers before rendering', function (): void {
        [$handler] = handleWithAdvanced(advancedWebEnvironment(production: false), new RuntimeException('Boom'));

        expect($handler->buffersClearedCount)->toBe(1);
    });

    it('renders JSON when the client accepts JSON', function (): void {
        [$handler, $output] = handleWithAdvanced(
            advancedWebEnvironment(production: true, server: ['HTTP_ACCEPT' => 'application/json']),
            new RuntimeException('secret'),
        );

        expect($handler->headersSent['Content-Type'])->toBe('application/json')
            ->and(json_decode($output, true))->toBe(['message' => 'Server Error']);
    });

    it('uses the status of an HttpExceptionInterface that reaches the handler', function (): void {
        [$handler, $output] = handleWithAdvanced(
            advancedWebEnvironment(production: true, server: ['HTTP_ACCEPT' => 'application/json']),
            new GoneException('internal'),
        );

        expect($handler->statusCodeSet)->toBe(410)
            ->and($handler->headersSent['X-Gone'])->toBe('yes')
            ->and(json_decode($output, true))->toBe(['message' => 'This resource is gone.']);
    });

    it('does not set a status code in CLI', function (): void {
        [$handler] = handleWithAdvanced(
            new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']),
            new RuntimeException('Boom'),
        );

        expect($handler->statusCodeSet)->toBeNull();
    });
});
