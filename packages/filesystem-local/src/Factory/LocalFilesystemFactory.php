<?php

declare(strict_types=1);

namespace Marko\Filesystem\Local\Factory;

use JsonException;
use Marko\Core\Path\ProjectPaths;
use Marko\Filesystem\Attributes\FilesystemDriver;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Local\Filesystem\LocalFilesystem;

#[FilesystemDriver('local')]
class LocalFilesystemFactory implements FilesystemDriverFactoryInterface
{
    public function __construct(
        private ProjectPaths $paths,
    ) {}

    /**
     * Relative disk paths resolve against the project root, never the working
     * directory (FPM, CGI and mod_php run from public/).
     *
     * @param array<string, mixed> $config
     * @throws JsonException|FilesystemException
     */
    public function create(
        array $config,
    ): FilesystemInterface {
        $path = $config['path'] ?? throw new FilesystemException(
            message: 'Missing path in disk configuration',
            context: json_encode($config, JSON_THROW_ON_ERROR),
            suggestion: 'Add a "path" key to your disk configuration',
        );

        $path = $this->paths->resolve($path);

        $isPublic = $config['public'] ?? false;

        return new LocalFilesystem(
            basePath: $path,
            isPublic: $isPublic,
        );
    }
}
