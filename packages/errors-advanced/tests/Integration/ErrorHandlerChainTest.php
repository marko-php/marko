<?php

declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsAdvanced\Tests\Fixtures\ClockedContainer;
use Marko\ErrorsAdvanced\Tests\Fixtures\OutputSafeAdvancedErrorHandler;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\SimpleErrorHandler;
use Marko\Testing\Fake\FakeClock;

describe('Error Handler Chain Integration', function () {
    it('full error handling flow works', function () {
        // Test complete flow: Exception -> ErrorReport -> AdvancedErrorHandler -> Formatter -> Output
        $exception = new RuntimeException('Integration test error', 500);
        $report = ErrorReport::fromThrowable($exception, Severity::Error, new DateTimeImmutable());

        // Verify ErrorReport captures exception data correctly
        expect($report->message)->toBe('Integration test error')
            ->and($report->code)->toBe(500)
            ->and($report->throwable)->toBe($exception)
            ->and($report->severity)->toBe(Severity::Error);

        // Test handler processes report and produces output
        $environment = new Environment(
            sapi: 'cli',
            envVars: ['MARKO_ENV' => 'development'],
        );
        $handler = new OutputSafeAdvancedErrorHandler(clock: new FakeClock(), environment: $environment);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // Verify complete flow produced expected output
        expect($output)->toContain('Integration test error')
            ->and($output)->toContain('RuntimeException')
            ->and($output)->toContain('Stack Trace:');
    });

    it('fallback to BasicHtmlFormatter works', function () {
        // Create formatter that throws to trigger fallback
        $failingFormatter = new class () implements FormatterInterface
        {
            public function format(
                ErrorReport $report,
            ): string {
                throw new RuntimeException('PrettyHtmlFormatter crashed');
            }
        };

        $environment = new Environment(
            sapi: 'apache',
            envVars: ['MARKO_ENV' => 'development'],
        );

        $handler = new OutputSafeAdvancedErrorHandler(
            clock: new FakeClock(),
            environment: $environment,
            prettyHtmlFormatter: $failingFormatter,
        );

        $report = ErrorReport::fromThrowable(
            new Exception('Fallback test error'),
            Severity::Error,
            new DateTimeImmutable(),
        );

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // BasicHtmlFormatter provides fallback output (simpler HTML without dark mode CSS)
        expect($output)->toContain('<!DOCTYPE html>')
            ->and($output)->toContain('Fallback test error')
            ->and($output)->not->toContain('prefers-color-scheme: dark');
    });

    it('CLI uses TextFormatter', function () {
        $environment = new Environment(
            sapi: 'cli',
            envVars: ['MARKO_ENV' => 'development'],
        );

        $handler = new OutputSafeAdvancedErrorHandler(clock: new FakeClock(), environment: $environment);

        $report = ErrorReport::fromThrowable(
            new Exception('CLI environment test'),
            Severity::Error,
            new DateTimeImmutable(),
        );

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // TextFormatter produces plain text with stack trace header
        expect($output)->toContain('CLI environment test')
            ->and($output)->toContain('Stack Trace:')
            ->and($output)->toContain('Exception')
            ->and($output)->not->toContain('<html')
            ->and($output)->not->toContain('<!DOCTYPE');
    });

    it('web uses PrettyHtmlFormatter', function () {
        $environment = new Environment(
            sapi: 'apache',
            envVars: ['MARKO_ENV' => 'development'],
        );

        $handler = new OutputSafeAdvancedErrorHandler(clock: new FakeClock(), environment: $environment);

        $report = ErrorReport::fromThrowable(
            new Exception('Web environment test'),
            Severity::Error,
            new DateTimeImmutable(),
        );

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // PrettyHtmlFormatter produces HTML with dark mode CSS
        expect($output)->toContain('<!DOCTYPE html>')
            ->and($output)->toContain('Web environment test')
            ->and($output)->toContain('prefers-color-scheme: dark')
            ->and($output)->toContain('<style>');
    });

    it('module bindings resolve correctly', function () {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        // Bindings map interface to implementation, and the boot function is callable
        expect($config['bindings'])->toHaveKey(ErrorHandlerInterface::class)
            ->and(ClockedContainer::withModule($config)->get(ErrorHandlerInterface::class))
            ->toBeInstanceOf(AdvancedErrorHandler::class)
            ->and($config['boot'])->toBeCallable();

        // AdvancedErrorHandler implements the interface correctly
        $handler = new OutputSafeAdvancedErrorHandler(new FakeClock());
        expect($handler)->toBeInstanceOf(ErrorHandlerInterface::class);
    });

    it('preference system works', function () {
        // Both packages bind to the same interface
        $simpleModulePath = dirname(__DIR__, 3) . '/errors-simple/module.php';
        $advancedModulePath = dirname(__DIR__, 2) . '/module.php';

        $simpleConfig = require $simpleModulePath;
        $advancedConfig = require $advancedModulePath;

        // errors-simple binds SimpleErrorHandler; errors-advanced binds AdvancedErrorHandler (overrides simple)
        expect($simpleConfig['bindings'][ErrorHandlerInterface::class])
            ->toBe(SimpleErrorHandler::class)
            ->and(ClockedContainer::withModule($advancedConfig)->get(ErrorHandlerInterface::class))
            ->toBeInstanceOf(AdvancedErrorHandler::class);

        // Both handlers implement the same interface
        $environment = new Environment();
        $simpleHandler = new SimpleErrorHandler($environment, new FakeClock());
        $advancedHandler = new OutputSafeAdvancedErrorHandler(new FakeClock());

        expect($simpleHandler)->toBeInstanceOf(ErrorHandlerInterface::class)
            ->and($advancedHandler)->toBeInstanceOf(ErrorHandlerInterface::class);

        // When errors-advanced is loaded after errors-simple, its binding takes precedence
        // (Simulated by checking that AdvancedErrorHandler is the final binding)
        $mergedBindings = array_merge(
            $simpleConfig['bindings'],
            $advancedConfig['bindings'],
        );

        expect($mergedBindings[ErrorHandlerInterface::class])->toBe(AdvancedErrorHandler::class)
            ->and(ClockedContainer::withModule($advancedConfig)->get(ErrorHandlerInterface::class))
            ->toBeInstanceOf(AdvancedErrorHandler::class);
    });
});
