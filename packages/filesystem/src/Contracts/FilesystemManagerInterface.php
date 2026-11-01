<?php

declare(strict_types=1);

namespace Marko\Filesystem\Contracts;

interface FilesystemManagerInterface
{
    /**
     * Get a disk by name, or the default disk when the name is null.
     */
    public function disk(?string $name = null): FilesystemInterface;

    /**
     * Replace the named disk (the default disk when null) for the rest of the process.
     *
     * disk($name) returns this instance from now on, whether or not the disk was already built.
     */
    public function useDisk(
        ?string $name,
        FilesystemInterface $disk,
    ): void;
}
