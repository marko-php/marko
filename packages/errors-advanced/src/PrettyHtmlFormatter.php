<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced;

use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Throwable;

class PrettyHtmlFormatter implements FormatterInterface
{
    public function __construct(
        private ?SyntaxHighlighter $highlighter = null,
        private string $environment = 'development',
        private ?RequestDataCollector $requestCollector = null,
        private int $contextLines = 3,
    ) {
        $this->highlighter ??= new SyntaxHighlighter();
        $this->requestCollector ??= new RequestDataCollector();
    }

    public function format(
        ErrorReport $report,
    ): string {
        if ($this->isProduction()) {
            return $this->formatProduction();
        }

        return $this->formatDevelopment($report);
    }

    private function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    private function formatProduction(): string
    {
        $css = $this->getEmbeddedCss();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Error</title>
<style>$css</style>
</head>
<body>
<p class="message">An error occurred. Please try again later.</p>
</body>
</html>
HTML;
    }

    private function formatDevelopment(
        ErrorReport $report,
    ): string {
        $message = $this->escape($report->message);
        $file = $this->escape($report->file);
        $line = $report->line;
        $codeSnippet = $this->formatCodeSnippet($report->file, $report->line);
        $css = $this->getEmbeddedCss();
        $stackTrace = $this->formatStackTrace($report->trace);
        $requestData = $this->formatRequestData();
        $previousException = $this->formatPreviousException($report->previous);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Error</title>
<style>$css</style>
</head>
<body>
<p class="message">$message</p>
<p class="location">$file:$line</p>
<pre class="code-snippet"><code>$codeSnippet</code></pre>
<div class="stack-trace">$stackTrace</div>
$previousException
<div class="request-data">$requestData</div>
</body>
</html>
HTML;
    }

    private function getEmbeddedCss(): string
    {
        return <<<'CSS'
body { font-family: sans-serif; padding: 20px; background: #f5f5f5; }
.message { font-size: 1.2em; color: #333; }
.location { color: #666; }
.code-snippet { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
.keyword { color: #0000ff; }
.string { color: #a31515; }
.variable { color: #001080; }
.comment { color: #008000; }
.number { color: #098658; }
@media (prefers-color-scheme: dark) {
body { background: #1e1e1e; color: #d4d4d4; }
.message { color: #d4d4d4; }
.location { color: #9cdcfe; }
.code-snippet { background: #252526; border-color: #3c3c3c; }
.keyword { color: #569cd6; }
.string { color: #ce9178; }
.variable { color: #9cdcfe; }
.comment { color: #6a9955; }
.number { color: #b5cea8; }
}
@media (max-width: 768px) {
body { padding: 10px; }
.message { font-size: 1em; }
.location { word-break: break-all; }
.code-snippet { font-size: 0.85em; }
}
CSS;
    }

    private function formatCodeSnippet(
        string $file,
        int $errorLine,
    ): string {
        if (!is_readable($file)) {
            return '';
        }

        $code = file_get_contents($file);

        if ($code === false) {
            return '';
        }

        return $this->highlighter->highlightWithContext($code, $errorLine, $this->contextLines);
    }

    private function escape(
        string $value,
    ): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<int, array<string, mixed>> $trace
     */
    private function formatStackTrace(
        array $trace,
    ): string {
        $frames = [];

        foreach ($trace as $frame) {
            $frames[] = $this->formatStackFrame($frame);
        }

        return implode("\n", $frames);
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function formatStackFrame(
        array $frame,
    ): string {
        $file = $frame['file'] ?? '';
        $line = $frame['line'] ?? 0;
        $function = $this->formatFunctionName($frame);
        $escapedFile = $this->escape($file ?: '[internal function]');
        $codeSnippet = $this->formatCodeSnippet($file, $line);

        return <<<HTML
<div class="stack-frame">
<span class="frame-function">$function</span>
<span class="frame-location">$escapedFile:$line</span>
<pre class="frame-code"><code>$codeSnippet</code></pre>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function formatFunctionName(
        array $frame,
    ): string {
        $function = $frame['function'] ?? '';
        $class = $frame['class'] ?? '';
        $type = $frame['type'] ?? '';

        if ($class !== '') {
            return $this->escape($class . $type . $function . '()');
        }

        if ($function !== '') {
            return $this->escape($function . '()');
        }

        return '{main}';
    }

    private function formatRequestData(): string
    {
        $data = $this->requestCollector->collect();
        $method = $this->escape($data['method']);
        $uri = $this->escape($data['uri']);
        $headers = $this->formatKeyValueTable($data['headers'], 'Headers');
        $query = $this->formatKeyValueTable($data['query'], 'Query Parameters');
        $post = $this->formatKeyValueTable($data['post'], 'POST Data');
        $server = $this->formatServerInfo($data['server']);

        return <<<HTML
<div class="request-info">
<h3>Request</h3>
<p><strong>$method</strong> $uri</p>
$headers
$query
$post
</div>
$server
HTML;
    }

    /**
     * @param array<string, string> $server
     */
    private function formatServerInfo(
        array $server,
    ): string {
        $phpVersion = $this->escape($server['php_version'] ?? '');
        $software = $this->escape($server['software'] ?? '');
        $name = $this->escape($server['name'] ?? '');

        $softwareHtml = $software !== '' ? "<p><strong>Server Software:</strong> $software</p>" : '';
        $nameHtml = $name !== '' ? "<p><strong>Server Name:</strong> $name</p>" : '';

        return <<<HTML
<div class="environment-info">
<h3>Environment</h3>
<p><strong>PHP Version:</strong> $phpVersion</p>
$softwareHtml
$nameHtml
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatKeyValueTable(
        array $data,
        string $title,
    ): string {
        if (empty($data)) {
            return '';
        }

        $rows = [];
        foreach ($data as $key => $value) {
            $escapedKey = $this->escape((string) $key);
            $escapedValue = $this->escape((string) $value);
            $rows[] = "<tr><td>$escapedKey</td><td>$escapedValue</td></tr>";
        }

        $tableRows = implode("\n", $rows);

        return <<<HTML
<div class="data-section">
<h4>$title</h4>
<table class="data-table">
$tableRows
</table>
</div>
HTML;
    }

    private function formatPreviousException(
        ?Throwable $previous,
    ): string {
        if ($previous === null) {
            return '';
        }

        $message = $this->escape($previous->getMessage());
        $file = $this->escape($previous->getFile());
        $line = $previous->getLine();
        $codeSnippet = $this->formatCodeSnippet($previous->getFile(), $line);

        return <<<HTML
<div class="previous-exception">
<h3>Previous Exception</h3>
<p class="message">$message</p>
<p class="location">$file:$line</p>
<pre class="code-snippet"><code>$codeSnippet</code></pre>
</div>
HTML;
    }
}
