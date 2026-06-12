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

/**
 * Minimal override that suppresses only I/O side-effects but lets handleNonFatal() run.
 * Used to verify handleNonFatal() is NOT silently dropped in web SAPI.
 */
class WebSapiNonFatalCapturingHandler extends SimpleErrorHandler
{
    /** @var string[] */
    public array $errorLogMessages = [];

    protected function clearOutputBuffers(): void
    {
        // No-op in tests
    }

    protected function setHttpStatusCode(
        int $code,
    ): void {
        // No-op in tests
    }

    protected function writeToErrorLog(
        string $message,
    ): void {
        $this->errorLogMessages[] = $message;
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
        expect($output)->toContain('Stack Trace')
            ->not->toContain('<html');
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
        expect($output)->toContain('<!DOCTYPE html>')
            ->toContain('<html');
    });

    it('creates ErrorReport from Throwable', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $exception = new Exception('Exception from handleException');

        ob_start();
        $handler->handleException($exception);
        $output = ob_get_clean();

        // handleException should create an ErrorReport and call handle()
        expect($output)->toContain('Exception from handleException')
            ->toContain('Exception');
    });

    it('creates ErrorReport from PHP error', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        // Ensure warnings are reported
        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        $handler->handleError(E_WARNING, 'Test PHP warning', '/test/file.php', 42);

        error_reporting($originalLevel);

        // handleError should create an ErrorReport via handleNonFatal (non-destructive)
        expect($handler->nonFatalReports)->toHaveCount(1)
            ->and($handler->nonFatalReports[0]->message)->toBe('Test PHP warning');
    });

    it('converts PHP errors to ErrorException', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        $handler->handleError(E_WARNING, 'Undefined variable', '/app/code.php', 100);

        error_reporting($originalLevel);

        // Warnings are routed through handleNonFatal as ErrorException
        expect($handler->nonFatalReports)->toHaveCount(1)
            ->and($handler->nonFatalReports[0]->message)->toBe('Undefined variable');
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

        // Should be handled but not written to stdout or clear buffers,
        // and should still report the error loudly via handleNonFatal.
        expect($result)->toBeTrue()
            ->and($output)->toBeEmpty()
            ->and($handler->buffersClearedCount)->toBe(0)
            ->and($handler->nonFatalReports)->toHaveCount(1)
            ->and($handler->nonFatalReports[0]->severity)->toBe(Severity::Deprecated)
            ->and($handler->nonFatalReports[0]->message)->toBe('Function xyz() is deprecated');
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

        expect($result)->toBeTrue()
            ->and($output)->toBeEmpty()
            ->and($handler->nonFatalReports)->toHaveCount(1)
            ->and($handler->nonFatalReports[0]->severity)->toBe(Severity::Notice);
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

        // Non-fatal errors are still reported, even in production
        expect($result)->toBeTrue()
            ->and($output)->toBeEmpty()
            ->and($handler->nonFatalReports)->toHaveCount(1);
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
        expect($output)->not->toBeEmpty()
            ->toContain('Stdout test');
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
        expect($output)->not->toBeEmpty()
            ->toContain('Web response test')
            ->toContain('<!DOCTYPE html>');
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
        expect($result)->toBeFalse()
            ->and($output)->toBeEmpty();
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
        expect($output)->toContain('Stack Trace')
            ->toContain('Development error details')
            ->toContain($report->file);
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
        // (an error ID for reference).
        expect($output)->not->toContain('Stack Trace')
            ->toContain($report->id);
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
        // (plain text, not formatted).
        expect($output)->toContain('Original error')
            ->not->toContain('Stack Trace');
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
        expect($handler->buffersClearedCount)->toBe(1)
            ->and($output)->toContain('Buffer test');
    });

    it('does not clear output buffers when handling a recoverable warning', function (): void {
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $handler->handleError(E_WARNING, 'Recoverable warning', '/test/file.php', 10);
        ob_end_clean();

        error_reporting($originalLevel);

        expect($handler->buffersClearedCount)->toBe(0);
    });

    it('does not replace the response with a 500 page on a recoverable warning', function (): void {
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        echo 'prior response content';
        $handler->handleError(E_WARNING, 'Recoverable warning', '/test/file.php', 10);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        expect($output)->toContain('prior response content')
            ->and($output)->not->toContain('<!DOCTYPE html>')
            ->and($handler->statusCodeSet)->toBeNull();
    });

    it('reports a non-fatal error in web SAPI instead of discarding it', function (): void {
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new WebSapiNonFatalCapturingHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        $handler->handleError(E_USER_NOTICE, 'Web SAPI notice test', '/test/file.php', 1);

        error_reporting($originalLevel);

        // Should have reported via error_log instead of silently discarding it
        expect($handler->errorLogMessages)->toHaveCount(1)
            ->and($handler->errorLogMessages[0])->toContain('Notice')
            ->and($handler->errorLogMessages[0])->toContain('Web SAPI notice test');
    });

    it('still renders a 500 page for an uncaught exception', function (): void {
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        ob_start();
        $handler->handleException(new Exception('Uncaught exception'));
        $output = ob_get_clean();

        expect($output)->toContain('<!DOCTYPE html>')
            ->and($handler->statusCodeSet)->toBe(500)
            ->and($handler->buffersClearedCount)->toBe(1);
    });

    it('still handles a fatal error on shutdown', function (): void {
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($environment);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        // handleShutdown should call handleError for fatal types, which calls handleException
        // We can test this by directly calling handleError with E_ERROR
        ob_start();
        $handler->handleError(E_ERROR, 'Fatal error', '/test/file.php', 1);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        // Fatal errors (E_ERROR) should still produce a 500 page
        expect($output)->toContain('<!DOCTYPE html>')
            ->and($handler->statusCodeSet)->toBe(500)
            ->and($handler->buffersClearedCount)->toBe(1);
    });
});
