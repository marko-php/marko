<?php

declare(strict_types=1);

namespace Marko\Media\Service;

use Marko\Media\Config\MediaConfig;
use Marko\Media\Contracts\UrlGeneratorInterface;
use Marko\Media\Entity\Media;

readonly class UrlGenerator implements UrlGeneratorInterface
{
    public function __construct(
        private MediaConfig $config,
    ) {}

    public function url(
        Media $media,
    ): string {
        return $this->config->urlPrefix() . '/' . $media->path;
    }
}
