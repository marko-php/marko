<?php

declare(strict_types=1);

use Marko\Mail\Attachment;
use Marko\Mail\Exceptions\MessageException;

describe('Attachment', function (): void {
    it('fromPath reads file content', function (): void {
        $testFile = sys_get_temp_dir() . '/test-attachment.txt';
        file_put_contents($testFile, 'Hello, World!');

        try {
            $attachment = Attachment::fromPath($testFile);

            expect($attachment->content)->toBe('Hello, World!');
        } finally {
            unlink($testFile);
        }
    });

    it('fromPath uses filename as default name', function (): void {
        $testFile = sys_get_temp_dir() . '/my-document.pdf';
        file_put_contents($testFile, 'PDF content');

        try {
            $attachment = Attachment::fromPath($testFile);

            expect($attachment->name)->toBe('my-document.pdf');
        } finally {
            unlink($testFile);
        }
    });

    it('fromPath throws MessageException for missing file', function (): void {
        expect(fn () => Attachment::fromPath('/nonexistent/file.txt'))
            ->toThrow(MessageException::class, "Attachment file not found: '/nonexistent/file.txt'");
    });

    it('fromPath detects mime type', function (): void {
        $testFile = sys_get_temp_dir() . '/test-image.png';
        // Create a minimal valid PNG file (8x8 transparent)
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAADklEQVQI12P4////GQYJAAAFRgF+O+bnIwAAAABJRU5ErkJggg==',
        );
        file_put_contents($testFile, $pngData);

        try {
            $attachment = Attachment::fromPath($testFile);

            expect($attachment->mimeType)->toBe('image/png');
        } finally {
            unlink($testFile);
        }
    });

    it('fromContent stores raw content', function (): void {
        $content = 'Raw binary data here';

        $attachment = Attachment::fromContent($content, 'data.bin');

        expect($attachment->content)->toBe('Raw binary data here')
            ->and($attachment->name)->toBe('data.bin')
            ->and($attachment->mimeType)->toBe('application/octet-stream');
    });

    it('inline sets content ID', function (): void {
        $testFile = sys_get_temp_dir() . '/inline-image.png';
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAADklEQVQI12P4////GQYJAAAFRgF+O+bnIwAAAABJRU5ErkJggg==',
        );
        file_put_contents($testFile, $pngData);

        try {
            $attachment = Attachment::inline($testFile, 'logo');

            expect($attachment->contentId)->toBe('logo')
                ->and($attachment->content)->toBe($pngData)
                ->and($attachment->name)->toBe('inline-image.png');
        } finally {
            unlink($testFile);
        }
    });

    it('is readonly', function (): void {
        $reflection = new ReflectionClass(Attachment::class);

        expect($reflection->isReadOnly())->toBeTrue();
    });

    it('detects various mime types', function (string $extension, string $content, string $expectedMime): void {
        $testFile = sys_get_temp_dir() . '/test-file.' . $extension;
        file_put_contents($testFile, $content);

        try {
            $attachment = Attachment::fromPath($testFile);

            expect($attachment->mimeType)->toBe($expectedMime);
        } finally {
            unlink($testFile);
        }
    })->with([
        'plain text file' => ['txt', 'Hello, World!', 'text/plain'],
        'HTML file' => ['html', '<!DOCTYPE html><html><body>Test</body></html>', 'text/html'],
        'JSON file' => ['json', '{"key": "value"}', 'application/json'],
        'JPEG image' => [
            'jpg',
            base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==',
            ),
            'image/jpeg',
        ],
        'GIF image' => [
            'gif',
            base64_decode('R0lGODlhAQABAIAAAAUEBAAAACH5BAEAAAEALAAAAAABAAEAAAICRAEAOw=='),
            'image/gif',
        ],
        'PDF document' => [
            'pdf',
            '%PDF-1.4' . "\n" . '1 0 obj<</Type/Catalog>>endobj',
            'application/pdf',
        ],
        'XML file' => ['xml', '<?xml version="1.0"?><root></root>', 'text/xml'],
    ]);

    it('handles special characters in filenames', function (string $filename): void {
        $testFile = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($testFile, 'Test content');

        try {
            $attachment = Attachment::fromPath($testFile);

            expect($attachment->name)->toBe($filename);
        } finally {
            unlink($testFile);
        }
    })->with([
        'spaces in filename' => ['my document.txt'],
        'unicode characters' => ['archivo_español.txt'],
        'multiple dots' => ['file.backup.2024.txt'],
        'hyphens and underscores' => ['my-file_name.txt'],
        'parentheses' => ['report (final).txt'],
        'ampersand' => ['tom & jerry.txt'],
        'plus sign' => ['file+extra.txt'],
        'at symbol' => ['email@backup.txt'],
        'exclamation mark' => ['urgent!.txt'],
        'numbers' => ['file123.txt'],
    ]);

    it('inline uses correct content disposition via contentId', function (): void {
        $testFile = sys_get_temp_dir() . '/inline-test.png';
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAADklEQVQI12P4////GQYJAAAFRgF+O+bnIwAAAABJRU5ErkJggg==',
        );
        file_put_contents($testFile, $pngData);

        try {
            $inline = Attachment::inline($testFile, 'my-logo');
            $regular = Attachment::fromPath($testFile);

            // Inline attachments have contentId set (indicating inline disposition)
            expect($inline->contentId)->toBe('my-logo')
                ->and($inline->name)->toBe('inline-test.png')
                ->and($inline->mimeType)->toBe('image/png');

            // Regular attachments have null contentId (indicating attachment disposition)
            expect($regular->contentId)->toBeNull()
                ->and($regular->name)->toBe('inline-test.png');
        } finally {
            unlink($testFile);
        }
    });

    it('inline allows custom name and mime type', function (): void {
        $testFile = sys_get_temp_dir() . '/inline-custom.txt';
        file_put_contents($testFile, 'Test content');

        try {
            $attachment = Attachment::inline(
                $testFile,
                'custom-id',
                'custom-name.jpg',
                'image/jpeg',
            );

            expect($attachment->contentId)->toBe('custom-id')
                ->and($attachment->name)->toBe('custom-name.jpg')
                ->and($attachment->mimeType)->toBe('image/jpeg');
        } finally {
            unlink($testFile);
        }
    });

    it('inline throws for non-existent file', function (): void {
        expect(fn () => Attachment::inline('/nonexistent/file.png', 'id'))
            ->toThrow(MessageException::class, "Attachment file not found: '/nonexistent/file.png'");
    });
});
