<?php

declare(strict_types=1);

namespace Marko\Media\Exceptions;

class UploadException extends MediaException
{
    public static function finfoUnavailable(): self
    {
        return new self(
            message: 'Cannot derive MIME type from file content: the fileinfo extension is not available',
            context: 'Attempted to call finfo_open() to detect MIME type from file bytes',
            suggestion: 'Enable the fileinfo PHP extension (ext-fileinfo) on this server',
        );
    }

    /**
     * @param array<string> $expectedExtensions
     */
    public static function mimeExtensionMismatch(
        string $derivedMimeType,
        string $declaredExtension,
        array $expectedExtensions,
    ): self {
        $expectedList = implode(', ', $expectedExtensions);

        return new self(
            message: "File extension '$declaredExtension' does not match the content-derived MIME type '$derivedMimeType'",
            context: "Content-derived MIME type '$derivedMimeType' expects one of these extensions: $expectedList",
            suggestion: 'Rename the file to use an extension matching its actual content, or upload the correct file type',
        );
    }

    /**
     * @param array<string> $allowed
     */
    public static function invalidMimeType(
        string $mimeType,
        array $allowed,
    ): self {
        $allowedList = implode(', ', $allowed);

        return new self(
            message: "MIME type '$mimeType' is not allowed for upload",
            context: "Received MIME type: $mimeType",
            suggestion: "Use one of the allowed MIME types: $allowedList",
        );
    }

    /**
     * @param array<string> $allowed
     */
    public static function invalidExtension(
        string $extension,
        array $allowed,
    ): self {
        $allowedList = implode(', ', $allowed);

        return new self(
            message: "File extension '$extension' is not allowed for upload",
            context: "Received file extension: $extension",
            suggestion: "Use one of the allowed extensions: $allowedList",
        );
    }

    public static function fileTooLarge(
        int $size,
        int $maxSize,
    ): self {
        return new self(
            message: "File size $size bytes exceeds the maximum allowed size of $maxSize bytes",
            context: "Uploaded file size: $size bytes",
            suggestion: "Upload a file smaller than $maxSize bytes or increase the max_file_size configuration",
        );
    }
}
