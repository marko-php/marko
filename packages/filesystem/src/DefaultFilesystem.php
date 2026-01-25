<?php

declare(strict_types=1);

namespace Marko\Filesystem;

use JsonException;
use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Manager\FilesystemManager;
use Marko\Filesystem\Values\FileInfo;

readonly class DefaultFilesystem implements FilesystemInterface
{
    public function __construct(
        private FilesystemManager $manager,
    ) {}

    /**
     * @throws JsonException|FilesystemException
     */
    public function exists(
        string $path,
    ): bool {
        return $this->manager->disk()->exists($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function isFile(
        string $path,
    ): bool {
        return $this->manager->disk()->isFile($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function isDirectory(
        string $path,
    ): bool {
        return $this->manager->disk()->isDirectory($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function info(
        string $path,
    ): FileInfo {
        return $this->manager->disk()->info($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function read(
        string $path,
    ): string {
        return $this->manager->disk()->read($path);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function readStream(
        string $path,
    ): mixed {
        return $this->manager->disk()->readStream($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function write(
        string $path,
        string $contents,
        array $options = [],
    ): bool {
        return $this->manager->disk()->write($path, $contents, $options);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function writeStream(
        string $path,
        mixed $resource,
        array $options = [],
    ): bool {
        return $this->manager->disk()->writeStream($path, $resource, $options);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function append(
        string $path,
        string $contents,
    ): bool {
        return $this->manager->disk()->append($path, $contents);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function delete(
        string $path,
    ): bool {
        return $this->manager->disk()->delete($path);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function copy(
        string $source,
        string $destination,
    ): bool {
        return $this->manager->disk()->copy($source, $destination);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function move(
        string $source,
        string $destination,
    ): bool {
        return $this->manager->disk()->move($source, $destination);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function size(
        string $path,
    ): int {
        return $this->manager->disk()->size($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function lastModified(
        string $path,
    ): int {
        return $this->manager->disk()->lastModified($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function mimeType(
        string $path,
    ): string {
        return $this->manager->disk()->mimeType($path);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function listDirectory(
        string $path = '/',
    ): DirectoryListingInterface {
        return $this->manager->disk()->listDirectory($path);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function makeDirectory(
        string $path,
    ): bool {
        return $this->manager->disk()->makeDirectory($path);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function deleteDirectory(
        string $path,
    ): bool {
        return $this->manager->disk()->deleteDirectory($path);
    }

    /**
     * @throws JsonException|FilesystemException
     */
    public function setVisibility(
        string $path,
        string $visibility,
    ): bool {
        return $this->manager->disk()->setVisibility($path, $visibility);
    }

    /**
     * @throws FilesystemException|JsonException
     */
    public function visibility(
        string $path,
    ): string {
        return $this->manager->disk()->visibility($path);
    }
}
