<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Tests\Unit\Formatters;

use Exception;
use Marko\Core\Exceptions\MarkoException;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\TextFormatter;

describe('TextFormatter', function (): void {
    it('formats error message with severity color', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // Should contain the red color code for errors
        expect($output)->toContain("\033[31m");
        // Should contain reset code
        expect($output)->toContain("\033[0m");
    });

    it('displays the exception class name', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        expect($output)->toContain('Exception');
    });

    it('displays the error message', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        expect($output)->toContain('Test error message');
    });

    it('displays file and line number', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        expect($output)->toContain($report->file);
        expect($output)->toContain((string) $report->line);
    });

    it('displays formatted stack trace', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // Should show Stack Trace header
        expect($output)->toContain('Stack Trace');
        // Should show at least the first frame of the trace
        expect($output)->toMatch('/#\d+/');
    });

    it('displays code snippet around error line', function (): void {
        // Test the current file which exists
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // Should have code snippet section - the formatter should display code from the file
        expect($output)->toContain('Code Snippet');
    });

    it('highlights the error line in code snippet', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // The error line should be highlighted with an arrow or marker
        expect($output)->toContain('-->');
    });

    it('includes line numbers in code snippet', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // Should include line numbers in the snippet
        $lineNum = $report->line;
        expect($output)->toMatch("/\\|\\s*$lineNum\\s*\\|/");
    });

    it('displays context when available from MarkoException', function (): void {
        $exception = new MarkoException(
            message: 'Configuration error',
            context: 'While loading module configuration from app/blog/module.php',
        );
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        expect($output)->toContain('Context:');
        expect($output)->toContain('While loading module configuration');
    });

    it('displays suggestion when available from MarkoException', function (): void {
        $exception = new MarkoException(
            message: 'Configuration error',
            context: '',
            suggestion: 'Check that the module.php file exists and is readable',
        );
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        expect($output)->toContain('Suggestion:');
        expect($output)->toContain('Check that the module.php file exists');
    });

    it('displays previous exception when present', function (): void {
        $previous = new Exception('Original database error');
        $exception = new Exception('Failed to load user', 0, $previous);
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        expect($output)->toContain('Previous Exception');
        expect($output)->toContain('Original database error');
    });

    it('detects ANSI support and disables colors when not available', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        // Non-CLI environment typically doesn't support ANSI
        $environment = new Environment(sapi: 'cgi', envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // Should NOT contain ANSI color codes
        expect($output)->not->toContain("\033[31m");
        expect($output)->not->toContain("\033[0m");
    });

    it('can be forced to disable colors', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        // CLI environment but colors explicitly disabled
        $environment = new Environment(sapi: 'cli', envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor, colorsEnabled: false);

        $output = $formatter->format($report);

        // Should NOT contain ANSI color codes even though CLI
        expect($output)->not->toContain("\033[31m");
        expect($output)->not->toContain("\033[0m");
    });

    it('truncates very long file paths for readability', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // The file path in the output should be truncated/shortened if very long
        // Test by checking the output contains a truncated form with ...
        // Or if path is short enough, it should be present as-is
        $longPath = '/very/long/path/that/goes/on/and/on/and/on/for/a/really/long/time/file.php';

        // The truncation should show the filename and some context
        // Check that full paths over a certain length get truncated
        expect($formatter->truncatePath($longPath, 50))->toContain('...');
        expect($formatter->truncatePath($longPath, 50))->toContain('file.php');
    });

    it('formats in development mode with full details', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // Development mode should include all details
        expect($output)->toContain('Stack Trace');
        expect($output)->toContain('Code Snippet');
        expect($output)->toContain($report->file);
    });

    it('formats in production mode with minimal output', function (): void {
        $exception = new Exception('Test error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        // Production mode - no MARKO_ENV set or set to production
        $environment = new Environment(envVars: ['MARKO_ENV' => 'production']);
        $extractor = new CodeSnippetExtractor();
        $formatter = new TextFormatter($environment, $extractor);

        $output = $formatter->format($report);

        // Production mode should hide sensitive details
        expect($output)->not->toContain('Stack Trace');
        expect($output)->not->toContain('Code Snippet');
        // Should still show basic error info
        expect($output)->toContain('Test error message');
        expect($output)->toContain($report->id);
    });
});
