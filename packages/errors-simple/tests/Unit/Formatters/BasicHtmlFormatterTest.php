<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Tests\Unit\Formatters;

use Exception;
use Marko\Core\Exceptions\MarkoException;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use ReflectionClass;

describe('BasicHtmlFormatter', function (): void {
    it('returns valid HTML document', function (): void {
        $exception = new Exception('Test error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        expect($html)->toContain('<!DOCTYPE html>');
        expect($html)->toContain('<html');
        expect($html)->toContain('</html>');
        expect($html)->toContain('<head>');
        expect($html)->toContain('</head>');
        expect($html)->toMatch('/<body[^>]*>/');
        expect($html)->toContain('</body>');
    });

    it('sets appropriate content type header constant', function (): void {
        expect(BasicHtmlFormatter::CONTENT_TYPE)->toBe('text/html; charset=UTF-8');
    });

    it('displays the exception class name', function (): void {
        $exception = new Exception('Test error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        expect($html)->toContain('Exception');
    });

    it('displays the error message', function (): void {
        $exception = new Exception('Something went wrong with the request');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        expect($html)->toContain('Something went wrong with the request');
    });

    it('displays file and line number', function (): void {
        $exception = new Exception('Test error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        expect($html)->toContain($report->file);
        expect($html)->toContain((string) $report->line);
    });

    it('displays formatted stack trace as table', function (): void {
        $exception = new Exception('Test error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        expect($html)->toContain('<table');
        expect($html)->toContain('</table>');
        expect($html)->toContain('<tr');
        expect($html)->toContain('<td');
    });

    it('displays code snippet with syntax highlighting', function (): void {
        // Create a temp file with PHP code
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $code = <<<'PHP'
<?php

function test(): void
{
    throw new Exception('Error');
}

test();
PHP;
        file_put_contents($tempFile, $code);

        try {
            // Create an exception that references our temp file
            $exception = new Exception('Test error');
            // Use reflection to set the file/line
            $reflection = new ReflectionClass($exception);
            $fileProperty = $reflection->getProperty('file');
            $fileProperty->setValue($exception, $tempFile);
            $lineProperty = $reflection->getProperty('line');
            $lineProperty->setValue($exception, 5);

            $report = ErrorReport::fromThrowable($exception, Severity::Error);
            $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
            $extractor = new CodeSnippetExtractor();

            $formatter = new BasicHtmlFormatter($environment, $extractor);
            $html = $formatter->format($report);

            // Should contain code snippet in a pre/code block
            expect($html)->toContain('<pre');
            expect($html)->toContain('<code');
            expect($html)->toContain('throw new Exception');
        } finally {
            unlink($tempFile);
        }
    });

    it('highlights the error line in code snippet', function (): void {
        // Create a temp file with PHP code
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $code = <<<'PHP'
<?php

function test(): void
{
    throw new Exception('Error');
}

test();
PHP;
        file_put_contents($tempFile, $code);

        try {
            $exception = new Exception('Test error');
            $reflection = new ReflectionClass($exception);
            $fileProperty = $reflection->getProperty('file');
            $fileProperty->setValue($exception, $tempFile);
            $lineProperty = $reflection->getProperty('line');
            $lineProperty->setValue($exception, 5);

            $report = ErrorReport::fromThrowable($exception, Severity::Error);
            $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
            $extractor = new CodeSnippetExtractor();

            $formatter = new BasicHtmlFormatter($environment, $extractor);
            $html = $formatter->format($report);

            // The error line should be highlighted with a special class or style
            expect($html)->toMatch('/class=["\'].*error-line.*["\']/');
        } finally {
            unlink($tempFile);
        }
    });

    it('includes line numbers in code snippet', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $code = <<<'PHP'
<?php

function test(): void
{
    throw new Exception('Error');
}

test();
PHP;
        file_put_contents($tempFile, $code);

        try {
            $exception = new Exception('Test error');
            $reflection = new ReflectionClass($exception);
            $fileProperty = $reflection->getProperty('file');
            $fileProperty->setValue($exception, $tempFile);
            $lineProperty = $reflection->getProperty('line');
            $lineProperty->setValue($exception, 5);

            $report = ErrorReport::fromThrowable($exception, Severity::Error);
            $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
            $extractor = new CodeSnippetExtractor();

            $formatter = new BasicHtmlFormatter($environment, $extractor);
            $html = $formatter->format($report);

            // Should include line numbers
            expect($html)->toMatch('/class=["\']line-number["\']/');
            // Should have the line number 5 (error line)
            expect($html)->toContain('>5<');
        } finally {
            unlink($tempFile);
        }
    });

    it('displays context when available from MarkoException', function (): void {
        $exception = new MarkoException(
            'Module failed to load',
            context: 'While loading module "blog" during application bootstrap',
        );
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Context should be displayed in a dedicated section
        expect($html)->toContain('Context');
        expect($html)->toContain('While loading module');
    });

    it('displays suggestion when available from MarkoException', function (): void {
        $exception = new MarkoException(
            'Configuration file not found',
            suggestion: 'Run "marko init" to create a default configuration file',
        );
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Suggestion should be displayed in a dedicated section
        expect($html)->toContain('Suggestion');
        expect($html)->toContain('Run "marko init"');
    });

    it('displays previous exception when present', function (): void {
        $previous = new Exception('Database connection failed');
        $exception = new Exception('Could not fetch user data', previous: $previous);
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Should display information about the previous exception
        expect($html)->toContain('Previous');
        expect($html)->toContain('Database connection failed');
    });

    it('escapes HTML entities in error messages', function (): void {
        $exception = new Exception('Error with <script>alert("XSS")</script> in message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Should not contain unescaped script tag
        expect($html)->not->toContain('<script>alert("XSS")</script>');
        // Should contain escaped version
        expect($html)->toContain('&lt;script&gt;');
    });

    it('escapes HTML entities in file paths', function (): void {
        $exception = new Exception('Test error');
        // Use reflection to set a malicious file path
        $reflection = new ReflectionClass($exception);
        $fileProperty = $reflection->getProperty('file');
        $fileProperty->setValue($exception, '/path/<script>evil</script>/file.php');

        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Should escape the path
        expect($html)->not->toContain('/path/<script>evil</script>/file.php');
        expect($html)->toContain('&lt;script&gt;');
    });

    it('escapes HTML entities in code snippets', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        // Create a file with HTML-like content that should be escaped
        $code = <<<'PHP'
<?php
echo '<script>alert("XSS")</script>';
PHP;
        file_put_contents($tempFile, $code);

        try {
            $exception = new Exception('Test error');
            $reflection = new ReflectionClass($exception);
            $fileProperty = $reflection->getProperty('file');
            $fileProperty->setValue($exception, $tempFile);
            $lineProperty = $reflection->getProperty('line');
            $lineProperty->setValue($exception, 2);

            $report = ErrorReport::fromThrowable($exception, Severity::Error);
            $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
            $extractor = new CodeSnippetExtractor();

            $formatter = new BasicHtmlFormatter($environment, $extractor);
            $html = $formatter->format($report);

            // Script tag in code should be escaped
            expect($html)->not->toContain("echo '<script>");
            expect($html)->toContain('&lt;script&gt;');
        } finally {
            unlink($tempFile);
        }
    });

    it('formats in development mode with full details', function (): void {
        $exception = new Exception('Detailed error message');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Development mode shows full details
        expect($html)->toContain('Detailed error message');
        expect($html)->toContain('Exception');
        expect($html)->toContain($report->file);
    });

    it('formats in production mode with generic message', function (): void {
        $exception = new Exception('Sensitive error details should not be shown');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'production']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Production mode shows generic message
        expect($html)->toContain('An error occurred');
        // Should NOT show sensitive details
        expect($html)->not->toContain('Sensitive error details should not be shown');
        expect($html)->not->toContain($report->file);
    });

    it('uses inline styles for reliability', function (): void {
        $exception = new Exception('Test error');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
        $extractor = new CodeSnippetExtractor();

        $formatter = new BasicHtmlFormatter($environment, $extractor);
        $html = $formatter->format($report);

        // Should use inline styles (style attribute) rather than external CSS
        expect($html)->toContain('style="');
        // Should NOT reference external stylesheet
        expect($html)->not->toContain('<link rel="stylesheet"');
    });

    it('uses monospace font for code', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $code = "<?php\necho 'hello';";
        file_put_contents($tempFile, $code);

        try {
            $exception = new Exception('Test error');
            $reflection = new ReflectionClass($exception);
            $fileProperty = $reflection->getProperty('file');
            $fileProperty->setValue($exception, $tempFile);
            $lineProperty = $reflection->getProperty('line');
            $lineProperty->setValue($exception, 2);

            $report = ErrorReport::fromThrowable($exception, Severity::Error);
            $environment = new Environment(envVars: ['MARKO_ENV' => 'development']);
            $extractor = new CodeSnippetExtractor();

            $formatter = new BasicHtmlFormatter($environment, $extractor);
            $html = $formatter->format($report);

            // Code sections should use monospace font
            expect($html)->toMatch('/font-family:\s*[^;]*monospace/i');
        } finally {
            unlink($tempFile);
        }
    });
});
