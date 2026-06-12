<?php

declare(strict_types=1);

namespace Marko\MediaImagick\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class ImagickConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @return array<string>
     * @throws ConfigNotFoundException
     */
    public function allowedRasterFormats(): array
    {
        return $this->config->getArray('media-imagick.allowed_raster_formats');
    }
}
