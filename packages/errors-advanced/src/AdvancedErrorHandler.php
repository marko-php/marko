<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced;

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

    public function handleError(
        int $level,
        string $message,
        string $file,
        int $line,
    ): bool {
        return true;
    }

    public function register(): void {}

    public function unregister(): void {}
}
