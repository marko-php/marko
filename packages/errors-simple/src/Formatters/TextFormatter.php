<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Formatters;

use Marko\Errors\ErrorReport;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;

class TextFormatter
{
    private bool $colorsEnabled;

    public function __construct(
        private Environment $environment,
        private CodeSnippetExtractor $codeSnippetExtractor,
        ?bool $colorsEnabled = null,
    ) {
        $this->colorsEnabled = $colorsEnabled ?? $this->detectAnsiSupport();
    }

    public function format(
        ErrorReport $report,
    ): string {
        if ($this->environment->isProduction()) {
            return $this->formatProduction($report);
        }

        return $this->formatDevelopment($report);
    }

    private function formatProduction(
        ErrorReport $report,
    ): string {
        $color = $this->colorsEnabled ? $report->severity->color() : '';
        $reset = $this->colorsEnabled ? "\033[0m" : '';

        $output = "{$color}An error occurred$reset\n";
        $output .= "$report->message\n";
        $output .= "Error ID: $report->id\n";

        return $output;
    }

    private function formatDevelopment(
        ErrorReport $report,
    ): string {
        $color = $this->colorsEnabled ? $report->severity->color() : '';
        $reset = $this->colorsEnabled ? "\033[0m" : '';
        $className = $report->throwable::class;

        $output = "$color$className$reset\n";
        $output .= "$report->message\n";
        $output .= "$report->file:$report->line\n\n";

        if ($report->context !== '') {
            $output .= "Context: $report->context\n\n";
        }

        if ($report->suggestion !== '') {
            $output .= "Suggestion: $report->suggestion\n\n";
        }

        $output .= $this->formatCodeSnippet($report->file, $report->line);
        $output .= $this->formatStackTrace($report->trace);

        if ($report->previous !== null) {
            $output .= $this->formatPreviousException($report->previous);
        }

        return $output;
    }

    private function formatPreviousException(
        \Throwable $previous,
    ): string {
        $className = $previous::class;
        $output = "\nPrevious Exception: $className\n";
        $output .= "{$previous->getMessage()}\n";
        $output .= "{$previous->getFile()}:{$previous->getLine()}\n";

        return $output;
    }

    private function formatCodeSnippet(
        string $file,
        int $line,
    ): string {
        $snippet = $this->codeSnippetExtractor->extract($file, $line, 2);

        if ($snippet['lines'] === []) {
            return '';
        }

        $errorLine = $snippet['errorLine'];
        $maxLineNumWidth = strlen((string) max(array_keys($snippet['lines'])));
        $output = "Code Snippet:\n";
        foreach ($snippet['lines'] as $lineNumber => $content) {
            $marker = $lineNumber === $errorLine ? '-->' : '   ';
            $paddedLineNum = str_pad((string) $lineNumber, $maxLineNumWidth, ' ', STR_PAD_LEFT);
            $output .= "$marker | $paddedLineNum | $content\n";
        }
        $output .= "\n";

        return $output;
    }

    /**
     * @param array<int, array<string, mixed>> $trace
     */
    private function formatStackTrace(
        array $trace,
    ): string {
        $output = "Stack Trace:\n";

        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';

            $call = $class !== '' ? "$class$type$function()" : "$function()";
            $output .= "#$index $file:$line $call\n";
        }

        return $output;
    }

    private function detectAnsiSupport(): bool
    {
        // CLI environment typically supports ANSI
        return $this->environment->isCli();
    }

    public function truncatePath(
        string $path,
        int $maxLength = 80,
    ): string {
        if (strlen($path) <= $maxLength) {
            return $path;
        }

        $filename = basename($path);
        $dirname = dirname($path);

        // Always keep the filename
        $availableForDir = $maxLength - strlen($filename) - 4; // 4 for ".../"

        if ($availableForDir <= 0) {
            return ".../$filename";
        }

        // Truncate directory from the left
        $truncatedDir = substr($dirname, -$availableForDir);

        return ".../$truncatedDir/$filename";
    }
}
