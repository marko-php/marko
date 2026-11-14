<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use finfo;
use Marko\Core\Contracts\UploadedFileInterface;
use Marko\Routing\Exceptions\UploadedFileException;

/**
 * A file uploaded with the request.
 *
 * Everything the client sent (filename, media type) is untrusted: use mimeType()
 * and guessExtension(), which inspect the file contents, for any decision that
 * matters. The file can be moved exactly once.
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * Preferred extensions for common MIME types, where finfo's own list starts with a less common one.
     */
    private const array EXTENSIONS = [
        'application/gzip' => 'gz',
        'application/json' => 'json',
        'application/msword' => 'doc',
        'application/pdf' => 'pdf',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/xml' => 'xml',
        'application/zip' => 'zip',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
        'text/csv' => 'csv',
        'text/html' => 'html',
        'text/plain' => 'txt',
        'text/xml' => 'xml',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
    ];

    private bool $moved = false;

    public function __construct(
        private readonly string $tempPath,
        private readonly string $clientFilename,
        private readonly string $clientMediaType,
        private readonly int $size,
        private readonly int $error = UPLOAD_ERR_OK,
    ) {}

    /**
     * The filename the client sent. Untrusted: never use it as a storage path.
     */
    public function clientFilename(): string
    {
        return $this->clientFilename;
    }

    /**
     * The media type the client sent. Untrusted: use mimeType() instead.
     */
    public function clientMediaType(): string
    {
        return $this->clientMediaType;
    }

    public function size(): int
    {
        return $this->size;
    }

    /**
     * The PHP upload error code (one of the UPLOAD_ERR_* constants).
     */
    public function error(): int
    {
        return $this->error;
    }

    public function tempPath(): string
    {
        return $this->tempPath;
    }

    /**
     * Whether the upload succeeded and the file has not been moved yet.
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && !$this->moved;
    }

    public function isMoved(): bool
    {
        return $this->moved;
    }

    /**
     * Move the uploaded file to its permanent location. Can be called once.
     *
     * Uses move_uploaded_file() under a web SAPI, so only genuine HTTP uploads can be
     * moved, and rename() elsewhere (CLI, tests, RoadRunner workers).
     *
     * @throws UploadedFileException
     */
    public function moveTo(
        string $targetPath,
    ): void {
        $this->assertUsable();

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory)) {
            throw UploadedFileException::moveFailed(
                $this->clientFilename,
                $targetPath,
                "The target directory '$targetDirectory' does not exist.",
            );
        }

        $moved = $this->isWebSapi()
            ? @move_uploaded_file($this->tempPath, $targetPath)
            : @rename($this->tempPath, $targetPath);

        if (!$moved) {
            throw UploadedFileException::moveFailed(
                $this->clientFilename,
                $targetPath,
                error_get_last()['message'] ?? 'The filesystem refused the move.',
            );
        }

        $this->moved = true;
    }

    /**
     * Open the uploaded file for reading. The caller closes the returned stream.
     *
     * @return resource
     * @throws UploadedFileException
     */
    public function stream(): mixed
    {
        $this->assertUsable();

        $stream = @fopen($this->tempPath, 'rb');

        if ($stream === false) {
            throw UploadedFileException::unreadable($this->clientFilename, $this->tempPath);
        }

        return $stream;
    }

    /**
     * @throws UploadedFileException
     */
    public function contents(): string
    {
        $this->assertUsable();

        $contents = @file_get_contents($this->tempPath);

        if ($contents === false) {
            throw UploadedFileException::unreadable($this->clientFilename, $this->tempPath);
        }

        return $contents;
    }

    /**
     * The real MIME type, detected from the file contents.
     *
     * @throws UploadedFileException
     */
    public function mimeType(): string
    {
        $this->assertUsable();

        $mimeType = new finfo(FILEINFO_MIME_TYPE)->file($this->tempPath);

        if ($mimeType === false) {
            throw UploadedFileException::unreadable($this->clientFilename, $this->tempPath);
        }

        return $mimeType;
    }

    /**
     * A file extension (without the dot) for the real MIME type, or null when it is unknown.
     *
     * @throws UploadedFileException
     */
    public function guessExtension(): ?string
    {
        $mimeType = $this->mimeType();

        if (isset(self::EXTENSIONS[$mimeType])) {
            return self::EXTENSIONS[$mimeType];
        }

        $extensions = new finfo(FILEINFO_EXTENSION)->file($this->tempPath);

        if (
            $extensions === false
            || $extensions === '???'
        ) {
            return null;
        }

        return explode('/', $extensions)[0];
    }

    /**
     * @throws UploadedFileException
     */
    private function assertUsable(): void
    {
        if ($this->moved) {
            throw UploadedFileException::alreadyMoved($this->clientFilename);
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw UploadedFileException::uploadFailed($this->clientFilename, $this->error);
        }
    }

    private function isWebSapi(): bool
    {
        return !in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }
}
