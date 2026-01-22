<?php

declare(strict_types=1);

namespace Marko\Mail;

use Marko\Mail\Exceptions\MessageException;

readonly class Attachment
{
    private function __construct(
        public string $content,
        public string $name,
        public string $mimeType,
        public ?string $contentId = null,
    ) {}

    public static function fromPath(
        string $path,
        ?string $name = null,
        ?string $mimeType = null,
    ): self {
        if (!file_exists($path)) {
            throw MessageException::attachmentNotFound($path);
        }

        return new self(
            content: file_get_contents($path),
            name: $name ?? basename($path),
            mimeType: $mimeType ?? mime_content_type($path) ?: 'application/octet-stream',
        );
    }

    public static function fromContent(
        string $content,
        string $name,
        string $mimeType = 'application/octet-stream',
    ): self {
        return new self(
            content: $content,
            name: $name,
            mimeType: $mimeType,
        );
    }

    public static function inline(
        string $path,
        string $contentId,
        ?string $name = null,
        ?string $mimeType = null,
    ): self {
        if (!file_exists($path)) {
            throw MessageException::attachmentNotFound($path);
        }

        return new self(
            content: file_get_contents($path),
            name: $name ?? basename($path),
            mimeType: $mimeType ?? mime_content_type($path) ?: 'application/octet-stream',
            contentId: $contentId,
        );
    }
}
