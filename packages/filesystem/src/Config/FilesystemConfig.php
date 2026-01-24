<?php

declare(strict_types=1);

namespace Marko\Filesystem\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Filesystem\Exceptions\FilesystemException;

readonly class FilesystemConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function defaultDisk(): string
    {
        return $this->config->getString('filesystem.default', 'local');
    }

    /**
     * @return array<string, mixed>
     * @throws FilesystemException
     */
    public function getDisk(
        string $name,
    ): array {
        $disks = $this->config->getArray('filesystem.disks', []);

        if (!isset($disks[$name])) {
            throw new FilesystemException(
                message: "Disk '$name' is not configured",
                context: 'Available disks: ' . implode(', ', array_keys($disks)),
                suggestion: 'Add disk configuration in config/filesystem.php or use an existing disk',
            );
        }

        return $disks[$name];
    }
}
