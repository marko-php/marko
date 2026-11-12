<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Environment\AppEnvironment;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsAdvanced\PrettyHtmlFormatter;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

describe('errors-advanced module arguments', function (): void {
    it('binds ErrorHandlerInterface to AdvancedErrorHandler as a class binding', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';

        expect($module['bindings'][ErrorHandlerInterface::class])->toBe(AdvancedErrorHandler::class);
    });

    it('builds AdvancedErrorHandler without a pretty html formatter', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
        $container = new Container(new PreferenceRegistry());
        $container->instance(ClockInterface::class, new FakeClock());
        $container->instance(AppEnvironment::class, new AppEnvironment(['APP_ENV' => 'local']));

        foreach ($module['bindings'] as $abstract => $concrete) {
            $container->bind($abstract, $concrete);
        }

        foreach ($module['arguments'] as $class => $arguments) {
            $container->setArguments($class, $arguments);
        }

        $handler = $container->get(ErrorHandlerInterface::class);

        expect($handler)->toBeInstanceOf(AdvancedErrorHandler::class)
            ->and(array_keys($module['arguments'][AdvancedErrorHandler::class]))->toBe(['prettyHtmlFormatter'])
            ->and(new ReflectionProperty(AdvancedErrorHandler::class, 'prettyHtmlFormatter')->getValue($handler))
            ->toBeInstanceOf(PrettyHtmlFormatter::class);
    });
});
