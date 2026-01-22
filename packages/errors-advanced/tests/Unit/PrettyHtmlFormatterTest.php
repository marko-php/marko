<?php

declare(strict_types=1);

use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsAdvanced\PrettyHtmlFormatter;

function createTestException(
    string $message = 'Test error message',
): Exception {
    return new Exception($message);
}

function createTestErrorReport(
    ?Exception $exception = null,
): ErrorReport {
    $exception ??= createTestException();

    return ErrorReport::fromThrowable($exception, Severity::Error);
}

describe('PrettyHtmlFormatter', function () {
    it('implements FormatterInterface', function () {
        $formatter = new PrettyHtmlFormatter();

        expect($formatter)->toBeInstanceOf(FormatterInterface::class);
    });

    it('formats ErrorReport to HTML', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('<html')
            ->and($output)->toContain('</html>');
    });

    it('includes exception message', function () {
        $formatter = new PrettyHtmlFormatter();
        $exception = createTestException('Something went wrong');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $output = $formatter->format($report);

        expect($output)->toContain('Something went wrong');
    });

    it('includes file and line number', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain($report->file)
            ->and($output)->toContain((string) $report->line);
    });

    it('includes code snippet', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Should contain syntax-highlighted code from SyntaxHighlighter
        expect($output)->toContain('<code')
            ->and($output)->toContain('</code>');
    });

    it('embeds CSS in HTML output', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('<style')
            ->and($output)->toContain('</style>');
    });

    it('produces valid HTML document', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('<!DOCTYPE html>')
            ->and($output)->toContain('<html>')
            ->and($output)->toContain('</html>')
            ->and($output)->toContain('<head>')
            ->and($output)->toContain('</head>')
            ->and($output)->toContain('<body>')
            ->and($output)->toContain('</body>')
            ->and($output)->toContain('<meta charset="UTF-8">');
    });
});
