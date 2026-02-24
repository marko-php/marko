<?php

declare(strict_types=1);

namespace Marko\Media\Exceptions;

class UploadException extends MediaException
{
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
