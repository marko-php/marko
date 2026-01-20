<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple\Tests\Unit;

use Marko\ErrorsSimple\CodeSnippetExtractor;

describe('CodeSnippetExtractor', function (): void {
    it('extracts lines around a given line number', function (): void {
        $extractor = new CodeSnippetExtractor();

        // Create a temp file with known content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "Line {$i} content";
        }
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            $result = $extractor->extract($tempFile, 10, 2);

            // Should extract lines 8-12 (2 before, target, 2 after)
            expect($result)->toHaveKey('lines');
            expect($result['lines'])->toHaveCount(5);
            expect($result['lines'][8])->toBe('Line 8 content');
            expect($result['lines'][9])->toBe('Line 9 content');
            expect($result['lines'][10])->toBe('Line 10 content');
            expect($result['lines'][11])->toBe('Line 11 content');
            expect($result['lines'][12])->toBe('Line 12 content');
        } finally {
            unlink($tempFile);
        }
    });

    it('returns configurable number of context lines before and after', function (): void {
        $extractor = new CodeSnippetExtractor();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "Line {$i} content";
        }
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            // Test with 3 lines of context
            $result = $extractor->extract($tempFile, 10, 3);

            // Should extract lines 7-13 (3 before, target, 3 after)
            expect($result['lines'])->toHaveCount(7);
            expect(array_keys($result['lines']))->toBe([7, 8, 9, 10, 11, 12, 13]);

            // Test with 1 line of context
            $result = $extractor->extract($tempFile, 10, 1);

            // Should extract lines 9-11 (1 before, target, 1 after)
            expect($result['lines'])->toHaveCount(3);
            expect(array_keys($result['lines']))->toBe([9, 10, 11]);
        } finally {
            unlink($tempFile);
        }
    });

    it('defaults to 5 lines of context on each side', function (): void {
        $extractor = new CodeSnippetExtractor();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "Line {$i} content";
        }
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            // Call without specifying context (should default to 5)
            $result = $extractor->extract($tempFile, 10);

            // Should extract lines 5-15 (5 before, target, 5 after)
            expect($result['lines'])->toHaveCount(11);
            expect(array_keys($result['lines']))->toBe([5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15]);
        } finally {
            unlink($tempFile);
        }
    });

    it('handles line numbers near start of file', function (): void {
        $extractor = new CodeSnippetExtractor();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "Line {$i} content";
        }
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            // Request line 2 with 5 lines of context
            $result = $extractor->extract($tempFile, 2, 5);

            // Should extract lines 1-7 (can't go before line 1)
            expect(array_keys($result['lines']))->toBe([1, 2, 3, 4, 5, 6, 7]);
            expect($result['lines'][1])->toBe('Line 1 content');
            expect($result['lines'][2])->toBe('Line 2 content');
        } finally {
            unlink($tempFile);
        }
    });

    it('handles line numbers near end of file', function (): void {
        $extractor = new CodeSnippetExtractor();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "Line {$i} content";
        }
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            // Request line 19 with 5 lines of context (file has 20 lines)
            $result = $extractor->extract($tempFile, 19, 5);

            // Should extract lines 14-20 (can't go past line 20)
            expect(array_keys($result['lines']))->toBe([14, 15, 16, 17, 18, 19, 20]);
            expect($result['lines'][19])->toBe('Line 19 content');
            expect($result['lines'][20])->toBe('Line 20 content');
        } finally {
            unlink($tempFile);
        }
    });

    it('returns empty array when file does not exist', function (): void {
        $extractor = new CodeSnippetExtractor();

        $result = $extractor->extract('/nonexistent/path/to/file.php', 10);

        expect($result['lines'])->toBe([]);
        expect($result['errorLine'])->toBe(10);
    });

    it('returns empty array when file is not readable', function (): void {
        $extractor = new CodeSnippetExtractor();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, "Line 1\nLine 2\nLine 3");
        chmod($tempFile, 0o000); // Remove all permissions

        try {
            $result = $extractor->extract($tempFile, 2);

            expect($result['lines'])->toBe([]);
            expect($result['errorLine'])->toBe(2);
        } finally {
            chmod($tempFile, 0o644); // Restore permissions for cleanup
            unlink($tempFile);
        }
    });

    it('preserves original line numbers as array keys', function (): void {
        $extractor = new CodeSnippetExtractor();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $lines[] = "Line {$i} content";
        }
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            $result = $extractor->extract($tempFile, 5, 1);

            // Array keys should be actual line numbers (4, 5, 6), not 0-indexed
            expect(array_keys($result['lines']))->toBe([4, 5, 6]);
            expect($result['lines'][4])->toBe('Line 4 content');
            expect($result['lines'][5])->toBe('Line 5 content');
            expect($result['lines'][6])->toBe('Line 6 content');

            // Ensure the keys are integers, not strings
            foreach (array_keys($result['lines']) as $key) {
                expect($key)->toBeInt();
            }
        } finally {
            unlink($tempFile);
        }
    });

    it('highlights the error line in returned data', function (): void {
        $extractor = new CodeSnippetExtractor();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $lines[] = "Line {$i} content";
        }
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            $result = $extractor->extract($tempFile, 7, 2);

            // The errorLine key should indicate which line is the error line
            expect($result)->toHaveKey('errorLine');
            expect($result['errorLine'])->toBe(7);

            // Verify the error line is included in the extracted lines
            expect($result['lines'])->toHaveKey(7);
        } finally {
            unlink($tempFile);
        }
    });

    it('handles files with fewer lines than context window', function (): void {
        $extractor = new CodeSnippetExtractor();

        // Create a file with only 3 lines
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, "Line 1\nLine 2\nLine 3");

        try {
            // Request line 2 with 5 lines of context (but file only has 3 lines)
            $result = $extractor->extract($tempFile, 2, 5);

            // Should return all 3 lines
            expect($result['lines'])->toHaveCount(3);
            expect(array_keys($result['lines']))->toBe([1, 2, 3]);
            expect($result['lines'][1])->toBe('Line 1');
            expect($result['lines'][2])->toBe('Line 2');
            expect($result['lines'][3])->toBe('Line 3');
        } finally {
            unlink($tempFile);
        }
    });

    it('trims trailing whitespace but preserves indentation', function (): void {
        $extractor = new CodeSnippetExtractor();

        // Create a file with indentation and trailing whitespace
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $content = "function test() {   \n" .   // trailing spaces
                   "    \$foo = 1;  \t\n" .     // indentation + trailing space/tab
                   "    return \$foo;   \n" .   // indentation + trailing spaces
                   '}   ';                       // trailing spaces
        file_put_contents($tempFile, $content);

        try {
            $result = $extractor->extract($tempFile, 2, 1);

            // Leading indentation should be preserved
            expect($result['lines'][1])->toBe('function test() {');
            expect($result['lines'][2])->toBe('    $foo = 1;');
            expect($result['lines'][3])->toBe('    return $foo;');
        } finally {
            unlink($tempFile);
        }
    });
});
