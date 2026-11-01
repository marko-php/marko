<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Error\BootstrapErrorHandler;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsSimple\Environment;
use Psr\Clock\ClockInterface;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        // Closure: the constructor's optional FormatterInterface cannot be
        // autowired, and the handler must be built with the application's
        // AppEnvironment so any non-development environment (including an
        // unset one) renders the safe generic page.
        ErrorHandlerInterface::class => function (ContainerInterface $container): ErrorHandlerInterface {
            return new AdvancedErrorHandler(
                clock: $container->get(ClockInterface::class),
                environment: new Environment(
                    appEnvironment: $container->get(AppEnvironment::class),
                ),
            );
        },
    ],
    // Shared, so the shutdown callback unregisters the instance boot registered
    'singletons' => [
        ErrorHandlerInterface::class,
    ],
    'boot' => function (ContainerInterface $container, BootstrapErrorHandler $bootstrapErrorHandler) {
        // Get the error handler and register it in place of core's bootstrap
        // handler, so the bootstrap handler is not left registered underneath
        $handler = $container->get(ErrorHandlerInterface::class);
        $bootstrapErrorHandler->unregister();
        $handler->register();
    },
    // Application::shutdown() runs this (marko/testing does after every test that booted an
    // application). It pops exactly the handlers boot registered, from the same shared instance.
    'shutdown' => function (ErrorHandlerInterface $handler): void {
        $handler->unregister();
    },
];
