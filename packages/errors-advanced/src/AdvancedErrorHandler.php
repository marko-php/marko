<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced;

use ErrorException;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Throwable;

class AdvancedErrorHandler implements ErrorHandlerInterface
{
    private Environment $environment;

    private FormatterInterface $prettyHtmlFormatter;

    private TextFormatter $textFormatter;

    private BasicHtmlFormatter $fallbackFormatter;

    protected bool $registered = false;

    protected mixed $previousExceptionHandler = null;

    protected mixed $previousErrorHandler = null;

    protected bool $handledFatalError = false;

    public function __construct(
        ?Environment $environment = null,
        ?FormatterInterface $prettyHtmlFormatter = null,
    ) {
        $this->environment = $environment ?? new Environment();
        $extractor = new CodeSnippetExtractor();
        $this->prettyHtmlFormatter = $prettyHtmlFormatter ?? new PrettyHtmlFormatter();
        $this->textFormatter = new TextFormatter(
            $this->environment,
            $extractor,
        );
        $this->fallbackFormatter = new BasicHtmlFormatter(
            $this->environment,
            $extractor,
        );
    }

    public function handle(
        ErrorReport $report,
    ): void {
        if ($this->environment->isCli()) {
            echo $this->textFormatter->format($report);

            return;
        }

        try {
            echo $this->prettyHtmlFormatter->format($report);
        } catch (Throwable) {
            echo $this->fallbackFormatter->format($report);
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

        $color = $report->severity->color();
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
        if (!(error_reporting() & $level)) {
            return false;
        }

        $exception = new ErrorException($message, 0, $level, $file, $line);
        $severity = Severity::fromErrorLevel($level);

        if ($severity === Severity::Deprecated || $severity === Severity::Notice) {
            $report = ErrorReport::fromThrowable($exception, $severity);
            $this->handleNonFatal($report);

            return true;
        }

        $this->handleException($exception);

        return true;
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

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        restore_exception_handler();
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        restore_error_handler();
        if ($this->previousErrorHandler !== null) {
            set_error_handler($this->previousErrorHandler);
        }

        $this->registered = false;
    }
}
