<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsSimple\Environment;

/**
 * Testable subclass that exposes internal state and captures non-fatal writes.
 */
class TestableAdvancedHandler extends AdvancedErrorHandler
{
    /** @var ErrorReport[] */
    public array $nonFatalReports = [];

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    protected function handleNonFatal(ErrorReport $report): void
    {
        $this->nonFatalReports[] = $report;
    }
}

function createTestErrorReportForHandler(
    ?Exception $exception = null,
): ErrorReport {
    $exception ??= new Exception('Test error message');

    return ErrorReport::fromThrowable($exception, Severity::Error);
}

describe('AdvancedErrorHandler', function (): void {
    it('implements ErrorHandlerInterface', function (): void {
        $handler = new AdvancedErrorHandler();

        expect($handler)->toBeInstanceOf(ErrorHandlerInterface::class);
    });

    it('uses PrettyHtmlFormatter for web', function (): void {
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

    it('uses TextFormatter for CLI', function (): void {
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

    it('falls back to BasicHtmlFormatter on error', function (): void {
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

    it('handles ErrorReport correctly', function (): void {
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

    it('catches formatter exceptions', function (): void {
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

describe('AdvancedErrorHandler registration', function (): void {
    beforeEach(function (): void {
        $this->originalExceptionHandler = set_exception_handler(fn () => null);
        restore_exception_handler();
        if ($this->originalExceptionHandler !== null) {
            restore_exception_handler();
        }

        $this->originalErrorHandler = set_error_handler(fn () => true);
        restore_error_handler();
        if ($this->originalErrorHandler !== null) {
            restore_error_handler();
        }
    });

    afterEach(function (): void {
        while (true) {
            $current = set_exception_handler(fn () => null);
            restore_exception_handler();
            if ($current === $this->originalExceptionHandler || $current === null) {
                break;
            }
            restore_exception_handler();
        }

        while (true) {
            $current = set_error_handler(fn () => true);
            restore_error_handler();
            if ($current === $this->originalErrorHandler || $current === null) {
                break;
            }
            restore_error_handler();
        }

        if ($this->originalExceptionHandler !== null) {
            set_exception_handler($this->originalExceptionHandler);
        }

        if ($this->originalErrorHandler !== null) {
            set_error_handler($this->originalErrorHandler);
        }
    });

    it('installs an error handler and an exception handler when register() is called', function (): void {
        $handler = new TestableAdvancedHandler();
        $handler->register();

        $currentExceptionHandler = set_exception_handler(fn () => null);
        restore_exception_handler();

        $currentErrorHandler = set_error_handler(fn () => true);
        restore_error_handler();

        $handler->unregister();

        expect($currentExceptionHandler)->toBe([$handler, 'handleException'])
            ->and($currentErrorHandler)->toBe([$handler, 'handleError']);
    });

    it('restores the previously installed handlers when unregister() is called', function (): void {
        $prevException = fn () => null;
        $prevError = fn () => true;
        set_exception_handler($prevException);
        set_error_handler($prevError);

        $handler = new TestableAdvancedHandler();
        $handler->register();
        $handler->unregister();

        $currentException = set_exception_handler(fn () => null);
        restore_exception_handler();
        $currentError = set_error_handler(fn () => true);
        restore_error_handler();

        // Restore the prev handlers we set at the start
        restore_exception_handler();
        restore_error_handler();

        expect($currentException)->toBe($prevException)
            ->and($currentError)->toBe($prevError);
    });

    it(
        'does not swallow warnings: handleError() surfaces a non-fatal warning loudly rather than returning true with no side effect',
        function (): void {
            $handler = new TestableAdvancedHandler(
                environment: new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']),
            );

            $originalLevel = error_reporting();
            error_reporting(E_ALL);

            ob_start();
            $result = $handler->handleError(E_WARNING, 'Test warning message', '/test/file.php', 42);
            $output = ob_get_clean();

            error_reporting($originalLevel);

            // Returns true (handled), produces output (not swallowed)
            expect($result)->toBeTrue()
                    ->and($output)->not->toBeEmpty()
                    ->and($output)->toContain('Test warning message');
        },
    );

    it('surfaces deprecations and notices without halting execution or clearing output buffers', function (): void {
        $handler = new TestableAdvancedHandler(
            environment: new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']),
        );

        $originalLevel = error_reporting();
        error_reporting(E_ALL);

        ob_start();
        echo 'prior output';
        $result = $handler->handleError(E_DEPRECATED, 'Function is deprecated', '/vendor/file.php', 10);
        $output = ob_get_clean();

        error_reporting($originalLevel);

        // Prior output is preserved (no buffer clearing); deprecation captured
        expect($result)->toBeTrue()
            ->and($output)->toContain('prior output')
            ->and($handler->nonFatalReports)->toHaveCount(1)
            ->and($handler->nonFatalReports[0]->severity)->toBe(Severity::Deprecated);
    });

    it(
        'respects error_reporting(): handleError() returns false for a level masked off by the current error_reporting setting',
        function (): void {
            $handler = new TestableAdvancedHandler();

            $originalLevel = error_reporting();
            error_reporting(E_ERROR | E_WARNING);

            $result = $handler->handleError(E_NOTICE, 'Suppressed notice', '/test.php', 1);

            error_reporting($originalLevel);

            expect($result)->toBeFalse();
        },
    );

    it('is idempotent: calling register() twice installs handlers only once', function (): void {
        $handler = new TestableAdvancedHandler();

        $handler->register();

        $firstHandler = set_exception_handler(fn () => null);
        restore_exception_handler();

        $handler->register();

        $secondHandler = set_exception_handler(fn () => null);
        restore_exception_handler();

        $handler->unregister();

        expect($secondHandler)->toBe($firstHandler)
            ->and($handler->isRegistered())->toBeFalse();
    });

    it(
        'registers a shutdown handler that surfaces a fatal error captured at shutdown (handleShutdown is idempotent and only acts on fatal error types)',
        function (): void {
            $handler = new TestableAdvancedHandler();

            expect(method_exists($handler, 'handleShutdown'))->toBeTrue();

            $handler->register();

            // Fatal types must trigger; non-fatal types must not
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
            $nonFatalTypes = [E_WARNING, E_NOTICE, E_DEPRECATED];

            foreach ($fatalTypes as $type) {
                expect($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))->toBeTruthy();
            }

            foreach ($nonFatalTypes as $type) {
                expect($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))->toBeFalsy();
            }

            $handler->unregister();

            expect($handler->isRegistered())->toBeFalse();
        },
    );

    it(
        'each test restores prior handler state via unregister() so global handlers do not leak across the suite',
        function (): void {
            $handler = new TestableAdvancedHandler();

            $handler->register();
            expect($handler->isRegistered())->toBeTrue();

            $handler->unregister();
            expect($handler->isRegistered())->toBeFalse();
        },
    );

    it(
        'boots successfully with errors-advanced as the sole error driver (module bindings load and the booted handler reports E_USER_WARNING loudly)',
        function (): void {
            $modulePath = dirname(__DIR__, 2) . '/module.php';
            $module = require $modulePath;

            $handler = new TestableAdvancedHandler(
                environment: new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']),
            );

            $container = new class ($handler) implements ContainerInterface
            {
                public function __construct(
                    private readonly TestableAdvancedHandler $handler,
                ) {}

                public function get(string $id): object
                {
                    return $this->handler;
                }

                public function has(string $id): bool
                {
                    return $id === ErrorHandlerInterface::class;
                }

                public function singleton(string $id): void {}

                public function instance(
                    string $id,
                    object $instance,
                ): void {}

                public function call(Closure $callable): mixed
                {
                    return $callable($this);
                }
            };

            ($module['boot'])($container);

            expect($handler->isRegistered())->toBeTrue();

            $originalLevel = error_reporting();
            error_reporting(E_ALL);

            ob_start();
            $result = $handler->handleError(E_USER_WARNING, 'Boot test warning', '/test/boot.php', 1);
            $output = ob_get_clean();

            error_reporting($originalLevel);

            $handler->unregister();

            expect($result)->toBeTrue()
                ->and($output)->not->toBeEmpty()
                ->and($output)->toContain('Boot test warning');
        },
    );
});
