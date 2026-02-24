<?php

declare(strict_types=1);

namespace Marko\Media\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class MediaConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function disk(): string
    {
        return $this->config->getString('media.disk');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function maxFileSize(): int
    {
        return $this->config->getInt('media.max_file_size');
    }

    /**
     * @return array<string>
     * @throws ConfigNotFoundException
     */
    public function allowedMimeTypes(): array
    {
        return $this->config->getArray('media.allowed_mime_types');
    }

    /**
     * @return array<string>
     * @throws ConfigNotFoundException
     */
    public function allowedExtensions(): array
    {
        return $this->config->getArray('media.allowed_extensions');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function urlPrefix(): string
    {
        return $this->config->getString('media.url_prefix');
    }
}
