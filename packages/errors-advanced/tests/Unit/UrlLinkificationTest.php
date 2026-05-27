<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsAdvanced\PrettyHtmlFormatter;
use Marko\ErrorsAdvanced\RequestDataCollector;

function createMinimalRequestCollector(): RequestDataCollector
{
    $data = [
        'method' => 'GET',
        'uri' => '/',
        'headers' => [],
        'query' => [],
        'post' => [],
        'server' => ['php_version' => '8.5.0', 'software' => '', 'name' => ''],
    ];

    return new class ($data) extends RequestDataCollector
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

function createMarkoExceptionWith(
    string $message = 'Test error',
    string $context = '',
    string $suggestion = '',
): MarkoException {
    return new class ($message, $context, $suggestion) extends MarkoException
    {
        public function __construct(
            string $message,
            private readonly string $contextText,
            private readonly string $suggestionText,
        ) {
            parent::__construct($message);
        }

        public function getContext(): string
        {
            return $this->contextText;
        }

        public function getSuggestion(): string
        {
            return $this->suggestionText;
        }
    };
}

function createFormatterWithReport(
    string $message = 'Test error',
    string $context = '',
    string $suggestion = '',
): array {
    $exception = createMarkoExceptionWith($message, $context, $suggestion);
    $report = ErrorReport::fromThrowable($exception, Severity::Error);
    $formatter = new PrettyHtmlFormatter(
        requestCollector: createMinimalRequestCollector(),
        environment: 'development',
    );

    return [$formatter, $report];
}

describe('URL Linkification - Context and Suggestion Rendering', function (): void {
    it('renders the context field in the HTML output when non-empty', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            context: 'This is helpful context about the error.',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('This is helpful context about the error.')
            ->and($output)->toContain('<p class="context">');
    });

    it('renders the suggestion field in the HTML output when non-empty', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            suggestion: 'Try running composer install to fix this.',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('Try running composer install to fix this.')
            ->and($output)->toContain('<p class="suggestion">');
    });

    it('omits empty context and suggestion blocks (does not render empty paragraphs)', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            context: '',
            suggestion: '',
        );

        $output = $formatter->format($report);

        expect($output)->not->toContain('<p class="context">')
            ->and($output)->not->toContain('<p class="suggestion">');
    });

    it('preserves newlines in the suggestion text via white-space pre-wrap', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            suggestion: "Line one\nLine two\nLine three",
        );

        $output = $formatter->format($report);

        expect($output)->toContain('<p class="suggestion">')
            ->and($output)->toMatch('/\.suggestion\s*\{[^}]*white-space:\s*pre-wrap/');
    });
});

describe('URL Linkification - URL Detection and Rendering', function (): void {
    it('renders http URLs as anchor tags with target blank and noopener noreferrer', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'See http://example.com for details',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('<a href="http://example.com"')
            ->and($output)->toContain('target="_blank"')
            ->and($output)->toContain('rel="noopener noreferrer"');
    });

    it('renders https URLs as anchor tags', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'See https://example.com/docs for details',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('<a href="https://example.com/docs"')
            ->and($output)->toContain('target="_blank"')
            ->and($output)->toContain('rel="noopener noreferrer"');
    });

    it('htmlspecialchars-escapes non-URL text portions', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'Error: <script>alert(1)</script>',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('&lt;script&gt;')
            ->and($output)->not->toContain('<script>alert(1)</script>');
    });

    it('preserves URLs inside mixed text correctly', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'Visit https://docs.example.com/guide and read the docs.',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('<a href="https://docs.example.com/guide"')
            ->and($output)->toContain('Visit ')
            ->and($output)->toContain(' and read the docs.');
    });

    it('does not linkify text that looks URL-ish but lacks a protocol (e.g., www.example.com without http)', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'Visit www.example.com for help',
        );

        $output = $formatter->format($report);

        expect($output)->not->toContain('<a href="')
            ->and($output)->toContain('www.example.com');
    });

    it('trims trailing punctuation from URL matches (period, comma, etc.)', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'See https://example.com/docs. For more info, visit https://example.com/help, and check https://example.com/faq!',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('<a href="https://example.com/docs"')
            ->and($output)->toContain('<a href="https://example.com/help"')
            ->and($output)->toContain('<a href="https://example.com/faq"')
            ->and($output)->not->toContain('href="https://example.com/docs."')
            ->and($output)->not->toContain('href="https://example.com/help,"')
            ->and($output)->not->toContain('href="https://example.com/faq!"');
    });

    it('escapes HTML special characters within the URL itself (defense against malformed input)', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'See https://example.com/search?q=a&b=c for results',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('https://example.com/search?q=a&amp;b=c')
            ->and($output)->not->toContain('href="https://example.com/search?q=a&b=c"');
    });

    it('does not double-escape when the input has no URLs', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            message: 'The value \'foo\' & "bar" are invalid',
        );

        $output = $formatter->format($report);

        expect($output)->toContain('&amp; &quot;bar&quot;')
            ->and($output)->not->toContain('&amp;amp;')
            ->and($output)->not->toContain('&amp;quot;');
    });

    it('linkifies URLs that appear in the suggestion field (NoDriverException docs URLs)', function (): void {
        [$formatter, $report] = createFormatterWithReport(
            suggestion: "Install the driver.\n\nSee https://marko.dev/docs/drivers for the full list.",
        );

        $output = $formatter->format($report);

        expect($output)->toContain('<p class="suggestion">')
            ->and($output)->toContain('<a href="https://marko.dev/docs/drivers"')
            ->and($output)->toContain('target="_blank"')
            ->and($output)->toContain('rel="noopener noreferrer"');
    });
});
