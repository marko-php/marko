<?php

declare(strict_types=1);

namespace Marko\Media\Contracts;

use Marko\Media\Entity\Media;
use Marko\Media\Value\UploadedFile;

interface MediaManagerInterface
{
    /**
     * Upload a file and return a Media entity.
     */
    public function upload(
        UploadedFile $file,
    ): Media;

    /**
     * Retrieve file contents for a Media entity.
     */
    public function retrieve(
        Media $media,
    ): string;

    /**
     * Delete the file associated with a Media entity.
     */
    public function delete(
        Media $media,
    ): void;

    /**
     * Check whether the file for a Media entity exists on the disk.
     */
    public function exists(
        Media $media,
    ): bool;
}
