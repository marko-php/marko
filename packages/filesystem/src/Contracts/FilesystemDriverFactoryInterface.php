<?php

declare(strict_types=1);

namespace Marko\Filesystem\Contracts;

interface FilesystemDriverFactoryInterface
{
    /**
     * Create a filesystem instance from the given configuration.
     *
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemInterface;
}
