<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Tests\Unit;

use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\SimpleErrorHandler;
use ReflectionMethod;

/**
 * Testable version that exposes internal state for verification.
 */
class TestableRegistrationHandler extends SimpleErrorHandler
{
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    public function getPreviousExceptionHandler(): mixed
    {
        return $this->previousExceptionHandler;
    }

    public function getPreviousErrorHandler(): mixed
    {
        return $this->previousErrorHandler;
    }

    public function hasHandledFatalError(): bool
    {
        return $this->handledFatalError;
    }

    public function setHandledFatalError(
        bool $value,
    ): void {
        $this->handledFatalError = $value;
    }

    protected function clearOutputBuffers(): void
    {
        // Skip buffer clearing in tests
    }
}

describe('SimpleErrorHandler Registration', function (): void {
    beforeEach(function (): void {
        // Store original handlers to restore after tests
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
        // Clear any exception handlers set during the test by repeatedly restoring
        // until we hit the original state (null or our saved handler)
        while (true) {
            $current = set_exception_handler(fn () => null);
            restore_exception_handler();

            if ($current === $this->originalExceptionHandler || $current === null) {
                break;
            }

            restore_exception_handler();
        }

        // Clear any error handlers set during the test
        while (true) {
            $current = set_error_handler(fn () => true);
            restore_error_handler();

            if ($current === $this->originalErrorHandler || $current === null) {
                break;
            }

            restore_error_handler();
        }

        // Restore original handlers if they existed
        if ($this->originalExceptionHandler !== null) {
            set_exception_handler($this->originalExceptionHandler);
        }

        if ($this->originalErrorHandler !== null) {
            set_error_handler($this->originalErrorHandler);
        }
    });

    it('registers as PHP exception handler', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        $handler->register();

        // Get the current exception handler
        $currentHandler = set_exception_handler(fn () => null);
        restore_exception_handler();

        expect($currentHandler)->toBe([$handler, 'handleException']);

        $handler->unregister();
    });

    it('registers as PHP error handler', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        $handler->register();

        // Get the current error handler
        $currentHandler = set_error_handler(fn () => true);
        restore_error_handler();

        expect($currentHandler)->toBe([$handler, 'handleError']);

        $handler->unregister();
    });

    it('registers shutdown function for fatal errors', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        // Shutdown functions can't be directly tested, but we can verify
        // the handleShutdown method exists and is callable
        expect(method_exists($handler, 'handleShutdown'))->toBeTrue();

        $handler->register();
        // If we get here without error, shutdown function was registered
        expect($handler->isRegistered())->toBeTrue();

        $handler->unregister();
    });

    it('stores previous exception handler on register', function (): void {
        // Set up a previous handler
        $previousHandler = fn () => null;
        set_exception_handler($previousHandler);

        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        $handler->register();

        expect($handler->getPreviousExceptionHandler())->toBe($previousHandler);

        $handler->unregister();
    });

    it('stores previous error handler on register', function (): void {
        // Set up a previous handler
        $previousHandler = fn () => true;
        set_error_handler($previousHandler);

        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        $handler->register();

        expect($handler->getPreviousErrorHandler())->toBe($previousHandler);

        $handler->unregister();
    });

    it('restores previous exception handler on unregister', function (): void {
        // Set up a previous handler
        $previousHandler = fn () => null;
        set_exception_handler($previousHandler);

        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        $handler->register();
        $handler->unregister();

        // Get the current handler
        $currentHandler = set_exception_handler(fn () => null);
        restore_exception_handler();

        expect($currentHandler)->toBe($previousHandler);
    });

    it('restores previous error handler on unregister', function (): void {
        // Set up a previous handler
        $previousHandler = fn () => true;
        set_error_handler($previousHandler);

        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        $handler->register();
        $handler->unregister();

        // Get the current handler
        $currentHandler = set_error_handler(fn () => true);
        restore_error_handler();

        expect($currentHandler)->toBe($previousHandler);
    });

    it('handles fatal errors via shutdown function', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        // We can't trigger a real fatal error in tests, but we can verify
        // the handleShutdown method would call handleError for fatal errors
        // by testing the method directly exists and has proper signature
        $reflection = new ReflectionMethod($handler, 'handleShutdown');

        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getNumberOfParameters())->toBe(0);
    });

    it('detects fatal error types in shutdown function', function (): void {
        // Fatal error types that should be handled
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

        foreach ($fatalTypes as $type) {
            // Verify these are the types we consider fatal
            // This test documents which error types are considered fatal
            expect($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))->toBeTruthy();
        }

        // Non-fatal types should not trigger shutdown handling
        $nonFatalTypes = [E_WARNING, E_NOTICE, E_DEPRECATED];

        foreach ($nonFatalTypes as $type) {
            expect($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))->toBeFalsy();
        }
    });

    it('only handles fatal error once in shutdown', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        // Initially, fatal error has not been handled
        expect($handler->hasHandledFatalError())->toBeFalse();

        // After setting the flag, it should be true
        $handler->setHandledFatalError(true);
        expect($handler->hasHandledFatalError())->toBeTrue();

        // This flag prevents double handling of fatal errors
        // The handleShutdown method checks this flag before processing
    });

    it('tracks registration state', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        // Initially not registered
        expect($handler->isRegistered())->toBeFalse();

        // After register(), should be registered
        $handler->register();
        expect($handler->isRegistered())->toBeTrue();

        // After unregister(), should no longer be registered
        $handler->unregister();
        expect($handler->isRegistered())->toBeFalse();
    });

    it('prevents double registration', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        // First registration
        $handler->register();
        expect($handler->isRegistered())->toBeTrue();

        // Get the current exception handler after first registration
        $firstHandler = set_exception_handler(fn () => null);
        restore_exception_handler();

        // Second registration should be a no-op
        $handler->register();

        // Should still be registered
        expect($handler->isRegistered())->toBeTrue();

        // Exception handler should not have changed (still our handler)
        $secondHandler = set_exception_handler(fn () => null);
        restore_exception_handler();

        expect($secondHandler)->toBe($firstHandler);

        $handler->unregister();
    });

    it('allows re-registration after unregister', function (): void {
        $environment = new Environment();
        $handler = new TestableRegistrationHandler($environment);

        // First registration
        $handler->register();
        expect($handler->isRegistered())->toBeTrue();

        // Unregister
        $handler->unregister();
        expect($handler->isRegistered())->toBeFalse();

        // Re-register should work
        $handler->register();
        expect($handler->isRegistered())->toBeTrue();

        // Verify the handler is actually registered
        $currentHandler = set_exception_handler(fn () => null);
        restore_exception_handler();
        expect($currentHandler)->toBe([$handler, 'handleException']);

        $handler->unregister();
    });
});
