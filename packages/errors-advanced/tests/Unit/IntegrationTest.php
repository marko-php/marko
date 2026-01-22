<?php

declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Marko\ErrorsSimple\SimpleErrorHandler;

describe('Integration with marko/errors-simple', function () {
    it('module.php declares bindings', function () {
        $modulePath = dirname(__DIR__, 2) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $config = require $modulePath;

        expect($config)->toBeArray()
            ->and($config)->toHaveKey('enabled')
            ->and($config['enabled'])->toBeTrue()
            ->and($config)->toHaveKey('bindings')
            ->and($config['bindings'])->toHaveKey(ErrorHandlerInterface::class)
            ->and($config['bindings'][ErrorHandlerInterface::class])->toBe(AdvancedErrorHandler::class);
    });

    it('can use BasicHtmlFormatter as fallback', function () {
        // Create a formatter that throws to trigger fallback
        $failingFormatter = new class () implements FormatterInterface
        {
            public function format(
                ErrorReport $report,
            ): string {
                throw new RuntimeException('Formatter failed');
            }
        };

        $environment = new Environment(
            sapi: 'apache',
            envVars: ['MARKO_ENV' => 'development'],
        );

        $handler = new AdvancedErrorHandler(
            environment: $environment,
            prettyHtmlFormatter: $failingFormatter,
        );

        $report = ErrorReport::fromThrowable(
            new Exception('Test error'),
            Severity::Error,
        );

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // BasicHtmlFormatter from errors-simple provides the fallback
        // It outputs simpler HTML without dark mode CSS
        expect($output)->toContain('<!DOCTYPE html>')
            ->and($output)->toContain('Test error')
            ->and($output)->not->toContain('prefers-color-scheme: dark');
    });

    it('can use TextFormatter from errors-simple', function () {
        $environment = new Environment(
            sapi: 'cli',
            envVars: ['MARKO_ENV' => 'development'],
        );

        $handler = new AdvancedErrorHandler(environment: $environment);

        $report = ErrorReport::fromThrowable(
            new Exception('CLI test error'),
            Severity::Error,
        );

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // TextFormatter from errors-simple is used in CLI mode
        expect($output)->toContain('CLI test error')
            ->and($output)->toContain('Stack Trace:')
            ->and($output)->not->toContain('<html');
    });

    it('preference overrides ErrorHandlerInterface', function () {
        // Load both module configurations
        $simpleModulePath = dirname(__DIR__, 3) . '/errors-simple/module.php';
        $advancedModulePath = dirname(__DIR__, 2) . '/module.php';

        $simpleConfig = require $simpleModulePath;
        $advancedConfig = require $advancedModulePath;

        // Both packages bind to the same interface
        expect($simpleConfig['bindings'])->toHaveKey(ErrorHandlerInterface::class)
            ->and($advancedConfig['bindings'])->toHaveKey(ErrorHandlerInterface::class);

        // errors-simple binds to SimpleErrorHandler
        expect($simpleConfig['bindings'][ErrorHandlerInterface::class])
            ->toBe(SimpleErrorHandler::class);

        // errors-advanced binds to AdvancedErrorHandler (overrides simple)
        expect($advancedConfig['bindings'][ErrorHandlerInterface::class])
            ->toBe(AdvancedErrorHandler::class);

        // Verify AdvancedErrorHandler is a valid implementation
        $handler = new AdvancedErrorHandler();
        expect($handler)->toBeInstanceOf(ErrorHandlerInterface::class);
    });

    it('packages can coexist', function () {
        // errors-advanced depends on errors-simple and uses its classes
        $composerJsonPath = dirname(__DIR__, 2) . '/composer.json';
        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        // Verify dependency on errors-simple
        expect($composerJson['require'])->toHaveKey('marko/errors-simple');

        // Can instantiate classes from both packages
        $environment = new Environment();
        $simpleHandler = new SimpleErrorHandler($environment);
        $advancedHandler = new AdvancedErrorHandler();

        expect($simpleHandler)->toBeInstanceOf(ErrorHandlerInterface::class)
            ->and($advancedHandler)->toBeInstanceOf(ErrorHandlerInterface::class);

        // AdvancedErrorHandler internally uses errors-simple classes
        $cliEnvironment = new Environment(
            sapi: 'cli',
            envVars: ['MARKO_ENV' => 'development'],
        );
        $codeExtractor = new CodeSnippetExtractor();
        $textFormatter = new TextFormatter(
            $cliEnvironment,
            $codeExtractor,
        );
        $basicHtmlFormatter = new BasicHtmlFormatter($cliEnvironment, $codeExtractor);

        // All classes can be instantiated and work together
        expect($textFormatter)->toBeInstanceOf(TextFormatter::class)
            ->and($basicHtmlFormatter)->toBeInstanceOf(BasicHtmlFormatter::class);

        // Can format the same error with different formatters
        $report = ErrorReport::fromThrowable(
            new Exception('Coexistence test'),
            Severity::Error,
        );

        $textOutput = $textFormatter->format($report);
        $htmlOutput = $basicHtmlFormatter->format($report);

        expect($textOutput)->toContain('Coexistence test')
            ->and($htmlOutput)->toContain('Coexistence test');
    });
});
