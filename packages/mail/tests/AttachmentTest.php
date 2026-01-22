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
            'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAADklEQVQI12P4////GQYJAAAFRgF+O+bnIwAAAABJRU5ErkJggg=='
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

        $attachment = Attachment::fromContent($content, 'data.bin', 'application/octet-stream');

        expect($attachment->content)->toBe('Raw binary data here')
            ->and($attachment->name)->toBe('data.bin')
            ->and($attachment->mimeType)->toBe('application/octet-stream');
    });

    it('inline sets content ID', function (): void {
        $testFile = sys_get_temp_dir() . '/inline-image.png';
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAADklEQVQI12P4////GQYJAAAFRgF+O+bnIwAAAABJRU5ErkJggg=='
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
});
