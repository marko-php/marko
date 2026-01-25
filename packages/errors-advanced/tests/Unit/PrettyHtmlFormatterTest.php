<?php

declare(strict_types=1);

use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsAdvanced\PrettyHtmlFormatter;
use Marko\ErrorsAdvanced\RequestDataCollector;

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

function createTestRequestCollector(
    array $data = [],
): RequestDataCollector {
    $defaults = [
        'method' => 'GET',
        'uri' => '/',
        'headers' => [],
        'query' => [],
        'post' => [],
        'server' => ['php_version' => '8.5.0', 'software' => '', 'name' => ''],
    ];

    $mergedData = array_merge($defaults, $data);

    return new class ($mergedData) extends RequestDataCollector
    {
        public function __construct(
            private readonly array $testData,
        ) {}

        public function collect(): array
        {
            return $this->testData;
        }
    };
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
            ->and($output)->toContain('<html lang="">')
            ->and($output)->toContain('</html>')
            ->and($output)->toContain('<head>')
            ->and($output)->toContain('</head>')
            ->and($output)->toContain('<body>')
            ->and($output)->toContain('</body>')
            ->and($output)->toContain('<meta charset="UTF-8">');
    });

    it('includes dark mode CSS via media query', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('@media (prefers-color-scheme: dark)');
    });

    it('includes light mode as default', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Light mode colors should be defined outside media query (as base styles)
        // Base background should be light (#f5f5f5)
        expect($output)->toMatch('/body\s*\{[^}]*background:\s*#f5f5f5/')
            ->and($output)->toMatch('/\.message\s*\{[^}]*color:\s*#333/');
    });

    it('uses prefers-color-scheme media query', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Verify the media query uses prefers-color-scheme (standard CSS for dark mode)
        expect($output)->toContain('prefers-color-scheme: dark')
            ->and($output)->not->toContain('prefers-color-scheme: light'); // Light is the default, not a media query
    });

    it('syntax highlighting colors work in both modes', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Light mode syntax highlighting colors (defined as base styles)
        expect($output)->toContain('.keyword { color: #0000ff; }')
            ->and($output)->toContain('.string { color: #a31515; }')
            ->and($output)->toContain('.variable { color: #001080; }')
            ->and($output)->toContain('.comment { color: #008000; }')
            ->and($output)->toContain('.number { color: #098658; }')
            // Dark mode should override these within the media query (different colors)
            ->and($output)->toContain('.keyword { color: #569cd6; }')
            ->and($output)->toContain('.string { color: #ce9178; }');
    });

    it('has responsive layout for mobile', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Should have viewport meta tag for mobile
        expect($output)->toContain('<meta name="viewport"')
            ->and($output)->toContain('width=device-width')
            // Should have responsive media query
            ->and($output)->toContain('@media (max-width:');
    });
});

describe('PrettyHtmlFormatter Environment Handling', function () {
    it('shows full details in development mode', function () {
        $formatter = new PrettyHtmlFormatter(
            environment: 'development',
        );
        $exception = createTestException('Database connection failed');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $output = $formatter->format($report);

        expect($output)->toContain('Database connection failed')
            ->and($output)->toContain($report->file)
            ->and($output)->toContain((string) $report->line)
            ->and($output)->toContain('<code');
    });

    it('shows generic message in production mode', function () {
        $formatter = new PrettyHtmlFormatter(
            environment: 'production',
        );
        $exception = createTestException('Database connection failed: user=admin password=secret123');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $output = $formatter->format($report);

        expect($output)->toContain('An error occurred')
            ->and($output)->not->toContain('Database connection failed')
            ->and($output)->not->toContain('secret123');
    });

    it('hides stack trace in production', function () {
        $formatter = new PrettyHtmlFormatter(
            environment: 'production',
        );
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->not->toContain('stack-trace')
            ->and($output)->not->toContain('stack-frame');
    });

    it('hides request data in production', function () {
        $collector = createTestRequestCollector([
            'method' => 'POST',
            'uri' => '/api/users/123',
            'headers' => ['Content-Type' => 'application/json'],
            'query' => ['page' => '1'],
            'post' => ['username' => 'admin'],
            'server' => ['php_version' => '8.5.0', 'software' => 'Apache', 'name' => 'localhost'],
        ]);

        $formatter = new PrettyHtmlFormatter(
            requestCollector: $collector,
            environment: 'production',
        );
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->not->toContain('POST')
            ->and($output)->not->toContain('/api/users/123')
            ->and($output)->not->toContain('application/json')
            ->and($output)->not->toContain('request-data');
    });

    it('respects environment configuration', function () {
        $devFormatter = new PrettyHtmlFormatter(
            environment: 'development',
        );
        $prodFormatter = new PrettyHtmlFormatter(
            environment: 'production',
        );
        $exception = createTestException('Sensitive error with DB credentials');
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $devOutput = $devFormatter->format($report);
        $prodOutput = $prodFormatter->format($report);

        // Development shows everything
        expect($devOutput)->toContain('Sensitive error with DB credentials')
            ->and($devOutput)->toContain($report->file)
            ->and($devOutput)->toContain('stack-trace')
            // Production hides everything sensitive
            ->and($prodOutput)->toContain('An error occurred')
            ->and($prodOutput)->not->toContain('Sensitive error with DB credentials')
            ->and($prodOutput)->not->toContain('stack-trace');
    });
});

describe('PrettyHtmlFormatter Stack Trace', function () {
    it('formats stack trace entries', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('stack-trace')
            ->and($output)->toContain('stack-frame');
    });

    it('shows file and line for each frame', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Each frame should contain file and line information
        // The trace includes this test file
        expect($output)->toContain('frame-location')
            ->and($output)->toContain(__FILE__);
    });

    it('highlights code at each frame', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Each frame should have syntax-highlighted code context
        expect($output)->toContain('frame-code')
            ->and($output)->toContain('<code');
    });

    it('shows function/method name', function () {
        $formatter = new PrettyHtmlFormatter();
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // The trace contains functions like createTestException and createTestErrorReport
        expect($output)->toContain('frame-function')
            ->and($output)->toContain('createTestException');
    });

    it('handles previous exceptions', function () {
        $formatter = new PrettyHtmlFormatter();
        $previous = new Exception('Original database error');
        $exception = new Exception('Failed to save user', 0, $previous);
        $report = ErrorReport::fromThrowable($exception, Severity::Error);

        $output = $formatter->format($report);

        // Should show the previous exception
        expect($output)->toContain('previous-exception')
            ->and($output)->toContain('Original database error');
    });

    it('limits context lines per frame', function () {
        $formatter = new PrettyHtmlFormatter(
            contextLines: 2,
        );
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // With only 2 context lines, there should be limited code shown per frame
        // The output should still contain frame code sections
        expect($output)->toContain('frame-code')
            ->and($output)->toContain('stack-frame');
    });
});

describe('PrettyHtmlFormatter Request Display', function () {
    it('displays request method and URI', function () {
        $collector = createTestRequestCollector([
            'method' => 'POST',
            'uri' => '/api/users/123',
        ]);

        $formatter = new PrettyHtmlFormatter(requestCollector: $collector);
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('POST')
            ->and($output)->toContain('/api/users/123');
    });

    it('displays request headers', function () {
        $collector = createTestRequestCollector([
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'text/html',
            ],
        ]);

        $formatter = new PrettyHtmlFormatter(requestCollector: $collector);
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('Content-Type')
            ->and($output)->toContain('application/json')
            ->and($output)->toContain('Accept')
            ->and($output)->toContain('text/html');
    });

    it('displays query parameters', function () {
        $collector = createTestRequestCollector([
            'query' => [
                'page' => '2',
                'sort' => 'name',
            ],
        ]);

        $formatter = new PrettyHtmlFormatter(requestCollector: $collector);
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('page')
            ->and($output)->toContain('2')
            ->and($output)->toContain('sort')
            ->and($output)->toContain('name');
    });

    it('displays POST data', function () {
        $collector = createTestRequestCollector([
            'post' => [
                'username' => 'john_doe',
                'email' => 'john@example.com',
            ],
        ]);

        $formatter = new PrettyHtmlFormatter(requestCollector: $collector);
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('username')
            ->and($output)->toContain('john_doe')
            ->and($output)->toContain('email')
            ->and($output)->toContain('john@example.com');
    });

    it('displays PHP version', function () {
        $collector = createTestRequestCollector([
            'server' => [
                'php_version' => '8.5.1',
                'software' => '',
                'name' => '',
            ],
        ]);

        $formatter = new PrettyHtmlFormatter(requestCollector: $collector);
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('PHP Version')
            ->and($output)->toContain('8.5.1');
    });

    it('displays server information', function () {
        $collector = createTestRequestCollector([
            'server' => [
                'php_version' => '8.5.0',
                'software' => 'nginx/1.24.0',
                'name' => 'api.example.com',
            ],
        ]);

        $formatter = new PrettyHtmlFormatter(requestCollector: $collector);
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        expect($output)->toContain('Server Software')
            ->and($output)->toContain('nginx/1.24.0')
            ->and($output)->toContain('Server Name')
            ->and($output)->toContain('api.example.com');
    });

    it('formats data in readable sections', function () {
        $collector = createTestRequestCollector([
            'method' => 'POST',
            'uri' => '/api/data',
            'headers' => ['Content-Type' => 'application/json'],
            'query' => ['debug' => 'true'],
            'post' => ['name' => 'test'],
            'server' => ['php_version' => '8.5.0', 'software' => 'Apache', 'name' => 'localhost'],
        ]);

        $formatter = new PrettyHtmlFormatter(requestCollector: $collector);
        $report = createTestErrorReport();

        $output = $formatter->format($report);

        // Verify sections are organized with proper headings
        expect($output)->toContain('<h3>Request</h3>')
            ->and($output)->toContain('<h4>Headers</h4>')
            ->and($output)->toContain('<h4>Query Parameters</h4>')
            ->and($output)->toContain('<h4>POST Data</h4>')
            ->and($output)->toContain('<h3>Environment</h3>')
            // Verify data is in tables for readability
            ->and($output)->toContain('<table class="data-table">');
    });
});
