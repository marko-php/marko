<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\ContentDispositionException;
use Marko\Routing\Http\ContentDisposition;
use Marko\Routing\Http\DispositionType;

describe('ContentDisposition', function (): void {
    it('builds an attachment with an ASCII filename', function (): void {
        $disposition = ContentDisposition::attachment('report.pdf');

        expect($disposition->type())->toBe(DispositionType::Attachment)
            ->and($disposition->filename())->toBe('report.pdf')
            ->and($disposition->toHeaderValue())->toBe('attachment; filename="report.pdf"');
    });

    it('builds inline without a filename', function (): void {
        $disposition = ContentDisposition::inline();

        expect($disposition->type())->toBe(DispositionType::Inline)
            ->and($disposition->filename())->toBeNull()
            ->and($disposition->toHeaderValue())->toBe('inline');
    });

    it('sends an ASCII fallback and a UTF-8 filename* for non-ASCII names', function (): void {
        expect(ContentDisposition::attachment('Résumé – 2026.pdf')->toHeaderValue())
            ->toBe(
                "attachment; filename=\"R_sum_ _ 2026.pdf\"; filename*=UTF-8''R%C3%A9sum%C3%A9%20%E2%80%93%202026.pdf",
            );
    });

    it('escapes quotes in the fallback filename', function (): void {
        expect(ContentDisposition::inline('say "hi".txt')->toHeaderValue())
            ->toBe('inline; filename="say \"hi\".txt"');
    });

    it('rejects unsafe filenames', function (string $filename): void {
        expect(fn () => ContentDisposition::attachment($filename))->toThrow(ContentDispositionException::class);
    })->with([
        'carriage return' => "a\rb.txt",
        'line feed' => "evil.txt\nSet-Cookie: x=1",
        'NUL' => "a\0b.txt",
        'slash' => '../etc/passwd',
        'backslash' => 'dir\\file.txt',
        'empty' => '',
    ]);

    it('round-trips toHeaderValue() through fromHeader()', function (string $filename): void {
        $parsed = ContentDisposition::fromHeader(ContentDisposition::attachment($filename)->toHeaderValue());

        expect($parsed->type())->toBe(DispositionType::Attachment)
            ->and($parsed->filename())->toBe($filename);
    })->with([
        'ASCII' => 'video.mp4',
        'ASCII with quotes' => 'the "best" cut.mp4',
        'UTF-8' => 'Résumé – 2026.pdf',
        'CJK' => '動画.mp4',
    ]);

    it('parses a plain or unquoted filename and a bare type', function (): void {
        expect(ContentDisposition::fromHeader('INLINE; filename=photo.jpg')->filename())->toBe('photo.jpg')
            ->and(ContentDisposition::fromHeader('Inline')->type())->toBe(DispositionType::Inline)
            ->and(ContentDisposition::fromHeader('attachment')->filename())->toBeNull();
    });

    it('prefers filename* over filename', function (): void {
        $parsed = ContentDisposition::fromHeader(
            "attachment; filename*=utf-8'en'na%C3%AFve.txt; filename=\"naive.txt\"",
        );

        expect($parsed->filename())->toBe('naïve.txt');
    });

    it('throws for an unknown type or a malformed filename*', function (): void {
        expect(fn () => ContentDisposition::fromHeader('form-data; name="file"'))->toThrow(
            ContentDispositionException::class,
        )
            ->and(fn () => ContentDisposition::fromHeader("attachment; filename*=latin1''x.txt"))->toThrow(
                ContentDispositionException::class,
            );
    });
});
