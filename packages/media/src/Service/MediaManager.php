<?php

declare(strict_types=1);

namespace Marko\Media\Service;

use JsonException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Filesystem\Contracts\FilesystemManagerInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Media\Config\MediaConfig;
use Marko\Media\Contracts\MediaManagerInterface;
use Marko\Media\Contracts\MediaRepositoryInterface;
use Marko\Media\Contracts\UploadedFileCheckerInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Exceptions\UploadException;
use Marko\Media\Value\UploadedFile;
use Psr\Clock\ClockInterface;

readonly class MediaManager implements MediaManagerInterface
{
    public function __construct(
        private FilesystemManagerInterface $filesystemManager,
        private MediaConfig $config,
        private MediaRepositoryInterface $repository,
        private ClockInterface $clock,
        private UploadedFileCheckerInterface $uploadedFileChecker,
    ) {}

    /**
     * @throws UploadException|ConfigNotFoundException|FilesystemException|JsonException
     */
    public function upload(
        UploadedFile $file,
    ): Media {
        if (!$this->uploadedFileChecker->isTrusted($file->tmpPath)) {
            throw UploadException::notAnUploadedFile($file->tmpPath);
        }

        if ($file->size > $this->config->maxFileSize()) {
            throw UploadException::fileTooLarge($file->size, $this->config->maxFileSize());
        }

        $derivedMimeType = $this->deriveContentMimeType($file->tmpPath);

        if (!in_array($derivedMimeType, $this->config->allowedMimeTypes(), true)) {
            throw UploadException::invalidMimeType($derivedMimeType, $this->config->allowedMimeTypes());
        }

        $mimeExtensionMap = $this->config->mimeExtensionMap();

        if (isset($mimeExtensionMap[$derivedMimeType]) && !in_array(
            $file->extension,
            $mimeExtensionMap[$derivedMimeType],
            true,
        )) {
            throw UploadException::mimeExtensionMismatch(
                $derivedMimeType,
                $file->extension,
                $mimeExtensionMap[$derivedMimeType],
            );
        }

        if (!in_array($file->extension, $this->config->allowedExtensions(), true)) {
            throw UploadException::invalidExtension($file->extension, $this->config->allowedExtensions());
        }

        $path = $this->clock->now()->format('Y/m') . '/' . uniqid() . '.' . $file->extension;

        $disk = $this->config->disk();

        $this->filesystemManager->disk($disk)->write($path, (string) file_get_contents($file->tmpPath));

        $media = new Media();
        $media->filename = basename($path);
        $media->originalFilename = $file->name;
        $media->mimeType = $derivedMimeType;
        $media->size = $file->size;
        $media->disk = $disk;
        $media->path = $path;

        return $this->repository->save($media);
    }

    /**
     * @throws UploadException
     */
    private function deriveContentMimeType(string $tmpPath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw UploadException::finfoUnavailable();
        }

        $mimeType = finfo_file($finfo, $tmpPath);

        if ($mimeType === false) {
            throw UploadException::finfoUnavailable();
        }

        return $mimeType;
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function retrieve(
        Media $media,
    ): string {
        return $this->filesystemManager->disk($media->disk)->read($media->path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function delete(
        Media $media,
    ): void {
        $this->filesystemManager->disk($media->disk)->delete($media->path);
        $this->repository->delete((int) $media->id);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function exists(
        Media $media,
    ): bool {
        return $this->filesystemManager->disk($media->disk)->exists($media->path);
    }
}
