<?php

declare(strict_types=1);

namespace Marko\Media\Service;

use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Media\Contracts\UrlGeneratorInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Exceptions\UrlGenerationException;

/**
 * Builds the public URL from the disk the media was actually stored on
 * (Media::$disk), using that disk's 'url' from config/filesystem.php, so the
 * URL always points at the place the file lives.
 */
readonly class UrlGenerator implements UrlGeneratorInterface
{
    public function __construct(
        private FilesystemConfig $filesystemConfig,
    ) {}

    /**
     * @throws UrlGenerationException|FilesystemException
     */
    public function url(
        Media $media,
    ): string {
        $diskConfig = $this->filesystemConfig->getDisk($media->disk);

        if (($diskConfig['public'] ?? false) !== true) {
            throw UrlGenerationException::diskNotPublic($media->disk, $media->path);
        }

        $baseUrl = $diskConfig['url'] ?? null;

        if (
            !is_string($baseUrl)
            || $baseUrl === ''
        ) {
            throw UrlGenerationException::diskHasNoUrl($media->disk, $media->path);
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($media->path, '/');
    }
}
