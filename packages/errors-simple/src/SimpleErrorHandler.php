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
            echo "Error: {$report->message}\n";
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
        $this->handleException($exception);

        return true;
    }

    public function register(): void {}

    public function unregister(): void {}
}
