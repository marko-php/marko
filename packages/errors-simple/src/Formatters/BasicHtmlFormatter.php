<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Formatters;

use Marko\Errors\ErrorReport;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Throwable;

class BasicHtmlFormatter
{
    public const string CONTENT_TYPE = 'text/html; charset=UTF-8';

    public function __construct(
        private Environment $environment,
        private CodeSnippetExtractor $extractor,
    ) {}

    public function format(
        ErrorReport $report,
    ): string {
        if ($this->environment->isProduction()) {
            return $this->formatProduction();
        }

        return $this->formatDevelopment($report);
    }

    private function formatProduction(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
</head>
<body>
<h1>Error</h1>
<p>An error occurred</p>
</body>
</html>
HTML;
    }

    private function formatDevelopment(
        ErrorReport $report,
    ): string {
        $exceptionClass = $this->escape($report->throwable::class);
        $message = $this->escape($report->message);
        $file = $this->escape($report->file);
        $line = $report->line;
        $stackTraceHtml = $this->formatStackTrace($report->trace);
        $codeSnippetHtml = $this->formatCodeSnippet($report->file, $line);
        $contextHtml = $this->formatContext($report->context);
        $suggestionHtml = $this->formatSuggestion($report->suggestion);
        $previousHtml = $this->formatPrevious($report->previous);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
</head>
<body style="font-family: sans-serif; padding: 20px; background: #f5f5f5;">
<h1 style="color: #c00;">$exceptionClass</h1>
<p style="font-size: 1.2em;">$message</p>
<p style="color: #666;">$file:$line</p>
$contextHtml
$suggestionHtml
$codeSnippetHtml
$stackTraceHtml
$previousHtml
</body>
</html>
HTML;
    }

    private function escape(
        string $value,
    ): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function formatStackTrace(
        array $trace,
    ): string {
        $rows = '';
        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $traceLine = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';
            $call = $class . $type . $function . '()';

            $rows .= "<tr><td>$index</td><td>$file:$traceLine</td><td>$call</td></tr>";
        }

        return "<table>$rows</table>";
    }

    private function formatCodeSnippet(
        string $file,
        int $errorLine,
    ): string {
        $snippet = $this->extractor->extract($file, $errorLine);

        if (empty($snippet['lines'])) {
            return '';
        }

        $lines = '';
        foreach ($snippet['lines'] as $lineNumber => $code) {
            $isErrorLine = $lineNumber === $snippet['errorLine'];
            $lineClass = $isErrorLine ? ' class="error-line"' : '';
            $escapedCode = $this->escape($code);
            $lines .= "<div$lineClass><span class=\"line-number\">$lineNumber</span>$escapedCode</div>";
        }

        return "<pre style=\"font-family: monospace; background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;\"><code>$lines</code></pre>";
    }

    private function formatContext(
        string $context,
    ): string {
        if ($context === '') {
            return '';
        }

        return "<div><strong>Context:</strong> $context</div>";
    }

    private function formatSuggestion(
        string $suggestion,
    ): string {
        if ($suggestion === '') {
            return '';
        }

        return "<div><strong>Suggestion:</strong> $suggestion</div>";
    }

    private function formatPrevious(
        ?Throwable $previous,
    ): string {
        if ($previous === null) {
            return '';
        }

        $class = $previous::class;
        $message = $previous->getMessage();

        return "<div><strong>Previous:</strong> $class: $message</div>";
    }
}
