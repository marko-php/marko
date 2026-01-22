<?php

declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsAdvanced\PrettyHtmlFormatter;
use Marko\ErrorsSimple\Environment;

function createTestErrorReportForHandler(
    ?Exception $exception = null,
): ErrorReport {
    $exception ??= new Exception('Test error message');

    return ErrorReport::fromThrowable($exception, Severity::Error);
}

describe('AdvancedErrorHandler', function () {
    it('implements ErrorHandlerInterface', function () {
        $handler = new AdvancedErrorHandler();

        expect($handler)->toBeInstanceOf(ErrorHandlerInterface::class);
    });

    it('uses PrettyHtmlFormatter for web', function () {
        $environment = new Environment(
            sapi: 'apache',
            envVars: ['MARKO_ENV' => 'development'],
        );
        $handler = new AdvancedErrorHandler(environment: $environment);
        $report = createTestErrorReportForHandler();

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // PrettyHtmlFormatter outputs HTML with specific CSS classes
        expect($output)->toContain('<html')
            ->and($output)->toContain('prefers-color-scheme: dark');
    });

    it('uses TextFormatter for CLI', function () {
        $environment = new Environment(
            sapi: 'cli',
            envVars: ['MARKO_ENV' => 'development'],
        );
        $handler = new AdvancedErrorHandler(environment: $environment);
        $report = createTestErrorReportForHandler();

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // TextFormatter outputs plain text with stack trace header
        expect($output)->toContain('Stack Trace:')
            ->and($output)->not->toContain('<html');
    });

    it('falls back to BasicHtmlFormatter on error', function () {
        // Create a PrettyHtmlFormatter that throws
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
        $report = createTestErrorReportForHandler();

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // BasicHtmlFormatter outputs simpler HTML without dark mode CSS
        expect($output)->toContain('<html')
            ->and($output)->toContain('Test error message')
            ->and($output)->not->toContain('prefers-color-scheme: dark');
    });

    it('handles ErrorReport correctly', function () {
        $environment = new Environment(
            sapi: 'cli',
            envVars: ['MARKO_ENV' => 'development'],
        );
        $handler = new AdvancedErrorHandler(environment: $environment);
        $exception = new Exception('Database connection failed');

        ob_start();
        $handler->handleException($exception);
        $output = ob_get_clean();

        // handleException should convert exception to ErrorReport and pass to handle()
        expect($output)->toContain('Database connection failed')
            ->and($output)->toContain('Exception')
            ->and($output)->toContain('Stack Trace:');
    });

    it('catches formatter exceptions', function () {
        // Create a formatter that always throws
        $failingFormatter = new class () implements FormatterInterface
        {
            public function format(
                ErrorReport $report,
            ): string {
                throw new RuntimeException('Formatter crashed unexpectedly');
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
        $report = createTestErrorReportForHandler();

        // Should not throw - exception should be caught and fallback used
        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // Should have output from fallback formatter, not throw
        expect($output)->toContain('<html')
            ->and($output)->toContain('Test error message');
    });
});
