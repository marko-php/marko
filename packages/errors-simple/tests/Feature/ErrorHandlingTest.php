<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Tests\Feature;

use Exception;
use Marko\Core\Exceptions\MarkoException;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Marko\ErrorsSimple\SimpleErrorHandler;
use ReflectionClass;
use RuntimeException;

/**
 * Test-friendly error handler that skips buffer clearing and HTTP headers.
 */
class TestableErrorHandler extends SimpleErrorHandler
{
    protected function clearOutputBuffers(): void
    {
        // Skip actual buffer clearing in tests to preserve Pest's buffers
    }

    protected function setHttpStatusCode(
        int $code,
    ): void {
        // Skip HTTP status code in tests
    }
}

describe('Error Handling Integration', function (): void {
    it('handles thrown exception in CLI context', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        ob_start();
        $handler->handleException(new RuntimeException('Test error'));
        $output = ob_get_clean();

        expect($output)->toContain('RuntimeException')
            ->and($output)->toContain('Test error');
    });

    it('handles thrown exception in web context', function (): void {
        $env = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        ob_start();
        $handler->handleException(new RuntimeException('Test web error'));
        $output = ob_get_clean();

        expect($output)->toContain('<!DOCTYPE html>')
            ->and($output)->toContain('RuntimeException')
            ->and($output)->toContain('Test web error');
    });

    it('handles PHP warning in CLI context', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $result = $handler->handleError(E_WARNING, 'Test warning message', '/test/file.php', 42);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        expect($result)->toBeTrue()
            ->and($output)->toContain('ErrorException')
            ->and($output)->toContain('Test warning message')
            ->and($output)->toContain('/test/file.php:42');
    });

    it('handles PHP warning in web context', function (): void {
        $env = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        $result = $handler->handleError(E_WARNING, 'Test web warning', '/web/file.php', 100);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        expect($result)->toBeTrue()
            ->and($output)->toContain('<!DOCTYPE html>')
            ->and($output)->toContain('ErrorException')
            ->and($output)->toContain('Test web warning');
    });

    it('handles MarkoException with context and suggestion', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        $exception = new MarkoException(
            message: 'Configuration error occurred',
            context: 'Loading module config',
            suggestion: 'Check your module.php file exists',
        );

        ob_start();
        $handler->handleException($exception);
        $output = ob_get_clean();

        expect($output)->toContain('MarkoException')
            ->and($output)->toContain('Configuration error occurred')
            ->and($output)->toContain('Context: Loading module config')
            ->and($output)->toContain('Suggestion: Check your module.php file exists');
    });

    it('handles nested exception with previous', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        $previousException = new RuntimeException('Original database error');
        $mainException = new Exception('Service initialization failed', 0, $previousException);

        ob_start();
        $handler->handleException($mainException);
        $output = ob_get_clean();

        expect($output)->toContain('Exception')
            ->and($output)->toContain('Service initialization failed')
            ->and($output)->toContain('Previous Exception: RuntimeException')
            ->and($output)->toContain('Original database error');
    });

    it('shows full details in development mode', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        $exception = new Exception('Detailed development error');

        ob_start();
        $handler->handleException($exception);
        $output = ob_get_clean();

        expect($output)->toContain('Exception')
            ->and($output)->toContain('Detailed development error')
            ->and($output)->toContain('Stack Trace')
            ->and($output)->toContain(__FILE__);
    });

    it('hides details in production mode', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'production']);
        $handler = new TestableErrorHandler($env);

        $exception = new Exception('Sensitive production error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        expect($output)->toContain('An error occurred')
            ->and($output)->toContain($report->id)
            ->and($output)->not->toContain('Stack Trace')
            ->and($output)->not->toContain(__FILE__);
    });

    it('extracts code snippet from error location', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new TestableErrorHandler($env);

        // Create exception that points to this file
        $exception = new Exception('Code snippet test');

        ob_start();
        $handler->handleException($exception);
        $output = ob_get_clean();

        // Output should contain code snippet with line markers
        expect($output)->toContain('Code Snippet:')
            ->and($output)->toContain('-->');
    });

    it('gracefully handles missing source file', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $result = $extractor->extract('/nonexistent/path/to/file.php', 10);

        expect($result['lines'])->toBeEmpty()
            ->and($result['errorLine'])->toBe(10);
    });

    it('can be resolved from container via interface', function (): void {
        // Simple mock container that returns the handler
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new SimpleErrorHandler($env);

        $container = new class ($handler)
        {
            public function __construct(
                private ErrorHandlerInterface $handler,
            ) {}

            public function get(
                string $id,
            ): object {
                if ($id === ErrorHandlerInterface::class) {
                    return $this->handler;
                }
                throw new Exception("Service not found: {$id}");
            }
        };

        $resolved = $container->get(ErrorHandlerInterface::class);

        expect($resolved)->toBeInstanceOf(ErrorHandlerInterface::class)
            ->and($resolved)->toBeInstanceOf(SimpleErrorHandler::class);
    });

    it('registers and unregisters cleanly', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $handler = new SimpleErrorHandler($env);

        // Verify handler implements required registration methods
        expect(method_exists($handler, 'register'))->toBeTrue()
            ->and(method_exists($handler, 'unregister'))->toBeTrue();

        // Use reflection to verify internal state tracking without actually registering
        // (registering would interfere with Pest's error handlers)
        $reflection = new ReflectionClass($handler);
        $registeredProperty = $reflection->getProperty('registered');

        // Initially not registered
        expect($registeredProperty->getValue($handler))->toBeFalse();

        // Calling unregister when not registered should be safe (no-op)
        $handler->unregister();
        expect($registeredProperty->getValue($handler))->toBeFalse();
    });

    it('falls back to plain text when formatter fails', function (): void {
        $env = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);

        // Create a failing formatter
        $failingFormatter = new class ($env, new CodeSnippetExtractor()) extends TextFormatter
        {
            public function format(
                ErrorReport $report,
            ): string {
                throw new RuntimeException('Formatter exploded!');
            }
        };

        $handler = new TestableErrorHandler($env, $failingFormatter);

        $exception = new Exception('Fallback test error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        ob_start();
        $handler->handle($report);
        $output = ob_get_clean();

        expect($output)->toContain('Error: Fallback test error')
            ->and($output)->not->toContain('Stack Trace')
            ->and($output)->not->toContain('Formatter exploded');
    });
});
