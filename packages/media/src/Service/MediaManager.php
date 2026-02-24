<?php

declare(strict_types=1);

namespace Marko\Media\Service;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Media\Config\MediaConfig;
use Marko\Media\Contracts\MediaManagerInterface;
use Marko\Media\Contracts\MediaRepositoryInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Exceptions\UploadException;
use Marko\Media\Value\UploadedFile;

readonly class MediaManager implements MediaManagerInterface
{
    public function __construct(
        private FilesystemInterface $filesystem,
        private MediaConfig $config,
        private MediaRepositoryInterface $repository,
    ) {}

    /**
     * @throws UploadException|ConfigNotFoundException
     */
    public function upload(
        UploadedFile $file,
    ): Media {
        if ($file->size > $this->config->maxFileSize()) {
            throw UploadException::fileTooLarge($file->size, $this->config->maxFileSize());
        }

        if (!in_array($file->mimeType, $this->config->allowedMimeTypes(), true)) {
            throw UploadException::invalidMimeType($file->mimeType, $this->config->allowedMimeTypes());
        }

        if (!in_array($file->extension, $this->config->allowedExtensions(), true)) {
            throw UploadException::invalidExtension($file->extension, $this->config->allowedExtensions());
        }

        $path = date('Y/m') . '/' . uniqid() . '.' . $file->extension;

        $this->filesystem->write($path, (string) file_get_contents($file->tmpPath));

        $media = new Media();
        $media->filename = basename($path);
        $media->originalFilename = $file->name;
        $media->mimeType = $file->mimeType;
        $media->size = $file->size;
        $media->disk = $this->config->disk();
        $media->path = $path;

        return $this->repository->save($media);
    }

    public function retrieve(
        Media $media,
    ): string {
        return $this->filesystem->read($media->path);
    }

    public function delete(
        Media $media,
    ): void {
        $this->filesystem->delete($media->path);
        $this->repository->delete((int) $media->id);
    }

    public function exists(
        Media $media,
    ): bool {
        return $this->filesystem->exists($media->path);
    }
}
