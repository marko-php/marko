<?php

declare(strict_types=1);

namespace Marko\Errors\Contracts;

use Marko\Errors\ErrorReport;
use Throwable;

/**
 * Contract for error handlers that process and respond to errors.
 *
 * Implementations of this interface are responsible for handling errors
 * that occur within the application, whether they originate from exceptions,
 * PHP errors, or other sources.
 */
interface ErrorHandlerInterface
{
    /**
     * Handle an error report.
     *
     * This method is the primary entry point for error handling. The ErrorReport
     * contains all relevant information about the error including the original
     * throwable, severity, context, and suggestions.
     *
     * @param ErrorReport $report The error report to handle
     */
    public function handle(ErrorReport $report): void;

    /**
     * Handle an uncaught exception.
     *
     * This method is called by PHP's exception handler when an exception
     * propagates to the top level without being caught. Implementations
     * should convert the exception to an ErrorReport and process it.
     *
     * @param Throwable $exception The uncaught exception to handle
     */
    public function handleException(Throwable $exception): void;

    /**
     * Handle a PHP error.
     *
     * This method is called by PHP's error handler when an error occurs.
     * The signature matches PHP's set_error_handler requirements.
     *
     * @param int $level The error level (E_WARNING, E_NOTICE, etc.)
     * @param string $message The error message
     * @param string $file The filename where the error occurred
     * @param int $line The line number where the error occurred
     * @return bool True if the error was handled, false to let PHP's default handler run
     */
    public function handleError(
        int $level,
        string $message,
        string $file,
        int $line,
    ): bool;

    /**
     * Register this handler with PHP's error and exception handlers.
     *
     * This method should call set_error_handler() and set_exception_handler()
     * to register this handler's methods with PHP. Implementations should
     * store references to previous handlers so they can be restored later.
     */
    public function register(): void;

    /**
     * Unregister this handler and restore previous handlers.
     *
     * This method should restore the error and exception handlers that were
     * active before register() was called. This is useful for testing or
     * when temporarily switching error handlers.
     */
    public function unregister(): void;
}
