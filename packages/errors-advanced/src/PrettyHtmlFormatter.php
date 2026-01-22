<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced;

use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;

class PrettyHtmlFormatter implements FormatterInterface
{
    public function __construct(
        private ?SyntaxHighlighter $highlighter = null,
    ) {
        $this->highlighter ??= new SyntaxHighlighter();
    }

    public function format(
        ErrorReport $report,
    ): string {
        $message = $this->escape($report->message);
        $file = $this->escape($report->file);
        $line = $report->line;
        $codeSnippet = $this->formatCodeSnippet($report->file, $report->line);
        $css = $this->getEmbeddedCss();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Error</title>
<style>$css</style>
</head>
<body>
<p class="message">$message</p>
<p class="location">$file:$line</p>
<pre class="code-snippet"><code>$codeSnippet</code></pre>
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

        return $this->highlighter->highlightWithContext($code, $errorLine);
    }

    private function escape(
        string $value,
    ): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
