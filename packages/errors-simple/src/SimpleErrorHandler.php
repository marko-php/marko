<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple;

use ErrorException;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Throwable;

class SimpleErrorHandler implements ErrorHandlerInterface
{
    private TextFormatter $textFormatter;

    private BasicHtmlFormatter $htmlFormatter;

    protected bool $registered = false;

    protected mixed $previousExceptionHandler = null;

    protected mixed $previousErrorHandler = null;

    protected bool $handledFatalError = false;

    public function __construct(
        private Environment $environment,
        ?TextFormatter $textFormatter = null,
        ?BasicHtmlFormatter $htmlFormatter = null,
    ) {
        $extractor = new CodeSnippetExtractor();
        $this->textFormatter = $textFormatter ?? new TextFormatter(
            $this->environment,
            $extractor,
        );
        $this->htmlFormatter = $htmlFormatter ?? new BasicHtmlFormatter(
            $this->environment,
            $extractor,
        );
    }

    public function handle(
        ErrorReport $report,
    ): void {
        // Clear output buffers before rendering error
        $this->clearOutputBuffers();

        try {
            if ($this->environment->isCli()) {
                echo $this->textFormatter->format($report);
            } else {
                $this->setHttpStatusCode(500);
                echo $this->htmlFormatter->format($report);
            }
        } catch (Throwable) {
            // Fall back to plain text if formatter fails
            echo "Error: $report->message\n";
        }
    }

    protected function clearOutputBuffers(): void
    {
        // Clear output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    protected function setHttpStatusCode(
        int $code,
    ): void {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    public function handleException(
        Throwable $exception,
    ): void {
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $this->handle($report);
    }

    protected function handleNonFatal(
        ErrorReport $report,
    ): void {
        if (!$this->environment->isCli()) {
            return;
        }

        $color = "\033[35m";
        $reset = "\033[0m";
        $label = $report->severity->label();

        fwrite(STDERR, "$color[$label]$reset $report->message in $report->file:$report->line\n");
    }

    public function handleError(
        int $level,
        string $message,
        string $file,
        int $line,
    ): bool {
        // Respect error_reporting level
        if (!(error_reporting() & $level)) {
            return false;
        }

        $exception = new ErrorException($message, 0, $level, $file, $line);
        $severity = Severity::fromErrorLevel($level);

        // Non-fatal errors (deprecations, notices) are reported loudly to stderr
        // but don't clear output buffers or halt execution.
        if ($severity === Severity::Deprecated || $severity === Severity::Notice) {
            $report = ErrorReport::fromThrowable($exception, $severity);
            $this->handleNonFatal($report);

            return true;
        }

        $this->handleException($exception);

        return true;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
        $this->registered = true;
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $fatalTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

        if (!($error['type'] & $fatalTypes)) {
            return;
        }

        if ($this->handledFatalError) {
            return;
        }

        $this->handledFatalError = true;
        $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        // Restore previous exception handler
        restore_exception_handler();
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        // Restore previous error handler
        restore_error_handler();
        if ($this->previousErrorHandler !== null) {
            set_error_handler($this->previousErrorHandler);
        }

        $this->registered = false;
    }
}
