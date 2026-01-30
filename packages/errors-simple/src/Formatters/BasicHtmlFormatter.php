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
<style>
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 20px;
        background: #f5f5f5;
        margin: 0;
        line-height: 1.5;
    }
    h1 { color: #c00; margin: 0 0 12px 0; font-size: 1.4em; word-break: break-word; }
    .message { font-size: 1.2em; margin: 0; color: #333; }
    .section { margin-bottom: 28px; }
    .callout {
        border-left: 4px solid #1a73e8;
        padding: 16px;
        margin-bottom: 28px;
        background: #e8f4fd;
    }
    .callout-neutral {
        background: #f8f8f8;
        border-left-color: #888;
    }
    .callout-neutral .callout-label { color: #555; }
    .callout-label {
        font-weight: 700;
        font-size: 1.1em;
        color: #1a73e8;
        margin-bottom: 6px;
    }
    .callout-text { color: #333; font-size: 1em; margin: 0; }
    .callout-text code {
        font-family: "SF Mono", Monaco, "Cascadia Code", monospace;
        font-size: 0.9em;
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 3px;
    }
    .callout-text ul { margin: 8px 0; padding-left: 24px; }
    .callout-text li { margin: 4px 0; }
    .code-wrapper {
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 12px;
    }
    .code-header {
        font-family: "SF Mono", Monaco, "Cascadia Code", monospace;
        font-size: 14px;
        font-weight: normal;
        background: #e8e8e8;
        padding: 8px 12px;
        color: #555;
        border-bottom: 1px solid #ddd;
    }
    .code-block {
        font-family: "SF Mono", Monaco, "Cascadia Code", monospace;
        font-size: 14px;
        font-weight: normal;
        color: #555;
        background: #fff;
        padding: 12px;
        overflow-x: auto;
        line-height: 2.0;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 12px;
        white-space: pre;
    }
    .code-wrapper .code-block {
        border: none;
        border-radius: 0;
        margin-top: 0;
    }
    .code-block .line-number {
        display: inline-block;
        width: 36px;
        color: #999;
        text-align: right;
        margin-right: 12px;
        user-select: none;
    }
    .code-block .error-line { background: #fee; font-weight: bold !important; color: #000 !important; }
    .stack-trace { margin: 0; white-space: pre; font-size: 14px; font-weight: normal; line-height: 1.8; }
    .stack-frame { display: block; padding: 3px 0; }
    .stack-frame:hover { background: #f0f0f0; }
    .stack-index { display: inline-block; width: 24px; color: #999; }
    .stack-location { color: #555; }
    .stack-call { color: #555; margin-left: 12px; }
    .previous-exception {
        background: #fff8e6;
        border-left: 4px solid #f59e0b;
        padding: 16px;
    }
    .previous-exception .callout-label { color: #b45309; }
</style>
</head>
<body>
<div class="container">
    <div class="section">
        <h1>$exceptionClass</h1>
        <p class="message"><strong>Error:</strong> $message</p>
    </div>
    $contextHtml
    $suggestionHtml
    $codeSnippetHtml
    $stackTraceHtml
    $previousHtml
</div>
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
        $frames = '';
        foreach ($trace as $index => $frame) {
            $file = $this->escape($frame['file'] ?? '[internal]');
            $traceLine = $frame['line'] ?? 0;
            $class = $this->escape($frame['class'] ?? '');
            $type = $this->escape($frame['type'] ?? '');
            $function = $this->escape($frame['function'] ?? '');
            $call = $class . $type . $function . '()';

            $frames .= "<span class=\"stack-frame\"><span class=\"stack-index\">$index</span><span class=\"stack-location\">$file:$traceLine</span><span class=\"stack-call\">$call</span></span>";
        }

        return <<<HTML
<div class="callout callout-neutral">
    <div class="callout-label">Stack trace</div>
    <div class="code-block"><div class="stack-trace">$frames</div></div>
</div>
HTML;
    }

    private function formatCodeSnippet(
        string $file,
        int $errorLine,
    ): string {
        $snippet = $this->extractor->extract($file, $errorLine);

        if (empty($snippet['lines'])) {
            return '';
        }

        $escapedFile = $this->escape($file);
        $lines = '';
        foreach ($snippet['lines'] as $lineNumber => $code) {
            $isErrorLine = $lineNumber === $snippet['errorLine'];
            $lineClass = $isErrorLine ? ' class="error-line"' : '';
            $escapedCode = $this->escape($code);
            $lines .= "<div$lineClass><span class=\"line-number\">$lineNumber</span>$escapedCode</div>";
        }

        return <<<HTML
<div class="callout callout-neutral">
    <div class="callout-label">Where it happens</div>
    <div class="code-wrapper"><div class="code-header">$escapedFile:$errorLine</div><div class="code-block">$lines</div></div>
</div>
HTML;
    }

    private function formatContext(
        string $context,
    ): string {
        if ($context === '') {
            return '';
        }

        $escaped = $this->escape($context);

        return <<<HTML
<div class="callout">
    <div class="callout-label">What is happening</div>
    <p class="callout-text">$escaped</p>
</div>
HTML;
    }

    private function formatSuggestion(
        string $suggestion,
    ): string {
        if ($suggestion === '') {
            return '';
        }

        $formatted = $this->formatSuggestionText($suggestion);

        return <<<HTML
<div class="callout">
    <div class="callout-label">How to fix it</div>
    <div class="callout-text">$formatted</div>
</div>
HTML;
    }

    /**
     * Format suggestion text with simple markdown-like syntax.
     *
     * Supports:
     * - `code` → <code>code</code>
     * - Lines starting with "- " → <ul><li>...</li></ul>
     * - Newlines → <br> (for non-list content)
     */
    private function formatSuggestionText(
        string $text,
    ): string {
        $lines = explode("\n", $text);
        $result = [];
        $inList = false;

        foreach ($lines as $line) {
            $trimmed = $line;

            // Check if line is a list item
            if (str_starts_with($trimmed, '- ')) {
                if (!$inList) {
                    $result[] = '<ul>';
                    $inList = true;
                }
                $itemContent = substr($trimmed, 2);
                $itemContent = $this->formatInlineCode($this->escape($itemContent));
                $result[] = "<li>$itemContent</li>";
            } else {
                if ($inList) {
                    $result[] = '</ul>';
                    $inList = false;
                }

                if ($trimmed === '') {
                    $result[] = '<br>';
                } else {
                    $formatted = $this->formatInlineCode($this->escape($trimmed));
                    $result[] = "<p style=\"margin: 0 0 4px 0;\">$formatted</p>";
                }
            }
        }

        if ($inList) {
            $result[] = '</ul>';
        }

        return implode("\n", $result);
    }

    /**
     * Convert `code` to <code>code</code>.
     */
    private function formatInlineCode(
        string $text,
    ): string {
        return preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    }

    private function formatPrevious(
        ?Throwable $previous,
    ): string {
        if ($previous === null) {
            return '';
        }

        $class = $this->escape($previous::class);
        $message = $this->escape($previous->getMessage());

        return <<<HTML
<div class="callout previous-exception">
    <div class="callout-label">Caused by</div>
    <p class="callout-text"><strong>$class</strong><br>$message</p>
</div>
HTML;
    }
}
