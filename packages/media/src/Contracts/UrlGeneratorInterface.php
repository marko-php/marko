<?php

declare(strict_types=1);

namespace Marko\Media\Contracts;

use Marko\Media\Entity\Media;

interface UrlGeneratorInterface
{
    /**
     * Generate a public URL for the given Media entity.
     */
    public function url(
        Media $media,
    ): string;
}
