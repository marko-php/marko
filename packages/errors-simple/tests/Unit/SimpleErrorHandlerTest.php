<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Tests\Unit;

use Exception;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Marko\ErrorsSimple\SimpleErrorHandler;
use RuntimeException;

/**
 * Test-friendly error handler that skips buffer clearing and captures non-fatal reports.
 */
class TestableErrorHandler extends SimpleErrorHandler
{
    public int $buffersClearedCount = 0;

    public ?int $statusCodeSet = null;

    /** @var ErrorReport[] */
    public array $nonFatalReports = [];

    protected function clearOutputBuffers(): void
    {
        $this->buffersClearedCount++;
        // Skip actual buffer clearing in tests to preserve Pest's buffers
    }

    protected function setHttpStatusCode(
        int $code,
    ): void {
        $this->statusCodeSet = $code;
    }

    protected function handleNonFatal(
        ErrorReport $report,
    ): void {
        $this->nonFatalReports[] = $report;
    }
}

describe('SimpleErrorHandler', function (): void {
    it('implements ErrorHandlerInterface', function (): void {
        $environment = new Environment();
        $handler = new SimpleErrorHandler($environment);

        expect($handler)->toBeInstanceOf(ErrorHandlerInterface::class);
    });

    it('accepts Environment dependency for context detection', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new SimpleErrorHandler($environment);

        // The handler accepts the environment and can be constructed
        expect($handler)->toBeInstanceOf(SimpleErrorHandler::class);
    });

    it('uses TextFormatter for CLI errors', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Test CLI error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // TextFormatter produces plain text with Stack Trace
        expect($output)->toContain('Stack Trace');
        expect($output)->not->toContain('<html');
    });

    it('uses BasicHtmlFormatter for web errors', function (): void {
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Test web error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // BasicHtmlFormatter produces HTML
        expect($output)->toContain('<!DOCTYPE html>');
        expect($output)->toContain('<html');
    });

    it('creates ErrorReport from Throwable', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Exception from handleException');

        ob_start();
        $handler->handleException($exception);
        $output = ob_get_clean();

        // handleException should create an ErrorReport and call handle()
        expect($output)->toContain('Exception from handleException');
        expect($output)->toContain('Exception');
    });

    it('creates ErrorReport from PHP error', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        // Ensure warnings are reported
        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $handler->handleError(E_WARNING, 'Test PHP warning', '/test/file.php', 42);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        // handleError should create an ErrorReport from the PHP error
        expect($output)->toContain('Test PHP warning');
        expect($output)->toContain('/test/file.php');
    });

    it('converts PHP errors to ErrorException', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $handler->handleError(E_WARNING, 'Undefined variable', '/app/code.php', 100);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        // The output should reference ErrorException
        expect($output)->toContain('ErrorException');
    });

    it('handles deprecation notices loudly but non-destructively', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $result = $handler->handleError(E_DEPRECATED, 'Function xyz() is deprecated', '/vendor/lib.php', 50);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        // Should be handled but not written to stdout or clear buffers
        expect($result)->toBeTrue();
        expect($output)->toBeEmpty();
        expect($handler->buffersClearedCount)->toBe(0);
        // Should still report the error loudly via handleNonFatal
        expect($handler->nonFatalReports)->toHaveCount(1);
        expect($handler->nonFatalReports[0]->severity)->toBe(Severity::Deprecated);
        expect($handler->nonFatalReports[0]->message)->toBe('Function xyz() is deprecated');
    });

    it('handles notice-level errors loudly but non-destructively', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $result = $handler->handleError(E_NOTICE, 'Undefined variable', '/app/code.php', 100);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        expect($result)->toBeTrue();
        expect($output)->toBeEmpty();
        expect($handler->nonFatalReports)->toHaveCount(1);
        expect($handler->nonFatalReports[0]->severity)->toBe(Severity::Notice);
    });

    it('reports deprecation notices in production too', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'production']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $result = $handler->handleError(E_DEPRECATED, 'Function xyz() is deprecated', '/vendor/lib.php', 50);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        expect($result)->toBeTrue();
        expect($output)->toBeEmpty();
        // Non-fatal errors are still reported, even in production
        expect($handler->nonFatalReports)->toHaveCount(1);
    });

    it('outputs formatted error to stdout in CLI', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Stdout test');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // Output should be captured (proving it went to stdout)
        expect($output)->not->toBeEmpty();
        expect($output)->toContain('Stdout test');
    });

    it('outputs formatted error to response in web', function (): void {
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Web response test');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // Output should be HTML response
        expect($output)->not->toBeEmpty();
        expect($output)->toContain('Web response test');
        expect($output)->toContain('<!DOCTYPE html>');
    });

    it('respects error_reporting level', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        // Save current error_reporting level
        $originalLevel = error_reporting();

        // Suppress notices
        error_reporting(E_ERROR | E_WARNING);

        ob_start();
        $result = $handler->handleError(E_NOTICE, 'Suppressed notice', '/test.php', 1);
        $output = ob_get_clean();

        // Restore original error_reporting level
        error_reporting($originalLevel);

        // The error should be silently ignored (return false)
        expect($result)->toBeFalse();
        expect($output)->toBeEmpty();
    });

    it('returns true from handleError when error is handled', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $result = $handler->handleError(E_WARNING, 'Handled warning', '/test.php', 1);
        ob_end_clean();

        error_reporting($originalLevel);

        expect($result)->toBeTrue();
    });

    it('shows full details in development mode', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Development error details');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // In development, should see stack trace, file info, and error details
        expect($output)->toContain('Stack Trace');
        expect($output)->toContain('Development error details');
        expect($output)->toContain($report->file);
    });

    it('shows generic message in production mode', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'production']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Sensitive internal error details');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // In production, should NOT see stack trace but should see basic error
        expect($output)->not->toContain('Stack Trace');
        // Should have an error ID for reference
        expect($output)->toContain($report->id);
    });

    it('catches exceptions in formatters and falls back to plain text', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);

        // Create a mock TextFormatter that throws an exception
        $failingFormatter = new class ($environment, new CodeSnippetExtractor()) extends TextFormatter
        {
            public function format(
                ErrorReport $report,
            ): string {
                throw new RuntimeException('Formatter failed!');
            }
        };

        $handler = new TestableErrorHandler($environment, $failingFormatter);

        $exception = new Exception('Original error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // Should fall back to plain text with the original error message
        expect($output)->toContain('Original error');
        // Should be plain text, not formatted
        expect($output)->not->toContain('Stack Trace');
    });

    it('sets HTTP 500 status code for web errors', function (): void {
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Web error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        ob_end_clean();

        expect($handler->statusCodeSet)->toBe(500);
    });

    it('clears output buffer before rendering error', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Buffer test');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        // Buffer clearing should have been called
        expect($handler->buffersClearedCount)->toBe(1);
        expect($output)->toContain('Buffer test');
    });
});
