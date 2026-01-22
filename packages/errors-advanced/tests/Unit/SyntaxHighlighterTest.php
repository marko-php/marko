<?php

declare(strict_types=1);

use Marko\ErrorsAdvanced\SyntaxHighlighter;

describe('SyntaxHighlighter', function () {
    it('highlights PHP code with spans', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php echo "Hello";';
        $result = $highlighter->highlight($code);

        expect($result)->toContain('<span')
            ->and($result)->toContain('</span>');
    });

    it('highlights PHP keywords', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php function myFunc() { return true; }';
        $result = $highlighter->highlight($code);

        expect($result)->toContain('<span class="keyword">function</span>')
            ->and($result)->toContain('<span class="keyword">return</span>');
    });

    it('highlights strings', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php $x = "hello world";';
        $result = $highlighter->highlight($code);

        expect($result)->toContain('class="string"')
            ->and($result)->toContain('hello world');
    });

    it('highlights comments', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php // This is a comment';
        $result = $highlighter->highlight($code);

        expect($result)->toContain('class="comment"')
            ->and($result)->toContain('This is a comment');
    });

    it('highlights variables', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php $myVariable = 1;';
        $result = $highlighter->highlight($code);

        expect($result)->toContain('<span class="variable">$myVariable</span>');
    });

    it('handles multi-line code', function () {
        $highlighter = new SyntaxHighlighter();

        $code = <<<'PHP'
<?php
function example() {
    $x = 1;
    return $x;
}
PHP;
        $result = $highlighter->highlight($code);

        expect($result)->toContain('class="keyword"')
            ->and($result)->toContain('class="variable"')
            ->and($result)->toContain("\n");
    });

    it('handles invalid syntax gracefully', function () {
        $highlighter = new SyntaxHighlighter();

        $invalidCode = '<?php function( { broken syntax';
        $result = $highlighter->highlight($invalidCode);

        expect($result)->toBeString()
            ->and($result)->not->toBeEmpty()
            ->and($result)->toContain('broken');
    });

    it('escapes special HTML characters', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php $x = "<script>alert(1)</script>";';
        $result = $highlighter->highlight($code);

        expect($result)->toContain('&lt;script&gt;')
            ->and($result)->toContain('&lt;/script&gt;')
            ->and($result)->not->toContain('<script>');
    });

    it('handles empty input', function () {
        $highlighter = new SyntaxHighlighter();

        $result = $highlighter->highlight('');

        expect($result)->toBe('');
    });

    it('identifies token types correctly', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php function test() { return "string"; }';
        $result = $highlighter->highlight($code);

        expect($result)->toContain('class="keyword"')
            ->and($result)->toContain('class="string"');
    });

    it('produces valid HTML output', function () {
        $highlighter = new SyntaxHighlighter();

        $code = '<?php $var = 123;';
        $result = $highlighter->highlight($code);

        $openTags = preg_match_all('/<span/', $result);
        $closeTags = preg_match_all('/<\/span>/', $result);

        expect($openTags)->toBe($closeTags)
            ->and($result)->not->toContain('<<')
            ->and($result)->not->toContain('>>');
    });

    it('supports context lines around error', function () {
        $highlighter = new SyntaxHighlighter();

        // Line 1: <?php
        // Line 2: $line1 = 1;
        // Line 3: $line2 = 2;
        // Line 4: $line3 = 3;  <- error line
        // Line 5: $line4 = 4;
        // Line 6: $line5 = 5;
        $code = <<<'PHP'
<?php
$line1 = 1;
$line2 = 2;
$line3 = 3;
$line4 = 4;
$line5 = 5;
PHP;

        $result = $highlighter->highlightWithContext(
            code: $code,
            errorLine: 4,
            contextLines: 1,
        );

        // With errorLine=4 and contextLines=1, we get lines 3,4,5
        // Which contain: $line2, $line3, $line4
        expect($result)->toContain('line2')
            ->and($result)->toContain('line3')
            ->and($result)->toContain('line4')
            ->and($result)->not->toContain('line1')
            ->and($result)->not->toContain('line5');
    });
});
