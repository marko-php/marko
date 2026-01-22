<?php

declare(strict_types=1);

namespace Marko\Filesystem\Contracts;

use Marko\Filesystem\Values\FileInfo;

interface FilesystemInterface
{
    /**
     * Check if a file or directory exists.
     */
    public function exists(string $path): bool;

    /**
     * Check if path is a file.
     */
    public function isFile(string $path): bool;

    /**
     * Check if path is a directory.
     */
    public function isDirectory(string $path): bool;

    /**
     * Get file information.
     */
    public function info(string $path): FileInfo;

    /**
     * Read file contents.
     */
    public function read(string $path): string;

    /**
     * Read file as a stream resource.
     *
     * @return resource
     */
    public function readStream(string $path): mixed;

    /**
     * Write contents to a file.
     *
     * @param array{visibility?: string} $options
     */
    public function write(
        string $path,
        string $contents,
        array $options = [],
    ): bool;

    /**
     * Write a stream to a file.
     *
     * @param resource $resource
     * @param array{visibility?: string} $options
     */
    public function writeStream(
        string $path,
        mixed $resource,
        array $options = [],
    ): bool;

    /**
     * Append contents to a file.
     */
    public function append(
        string $path,
        string $contents,
    ): bool;

    /**
     * Delete a file.
     */
    public function delete(string $path): bool;

    /**
     * Copy a file.
     */
    public function copy(
        string $source,
        string $destination,
    ): bool;

    /**
     * Move a file.
     */
    public function move(
        string $source,
        string $destination,
    ): bool;

    /**
     * Get file size in bytes.
     */
    public function size(string $path): int;

    /**
     * Get last modified timestamp.
     */
    public function lastModified(string $path): int;

    /**
     * Get MIME type.
     */
    public function mimeType(string $path): string;

    /**
     * List directory contents.
     */
    public function listDirectory(string $path = '/'): DirectoryListingInterface;

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path): bool;

    /**
     * Delete a directory and its contents.
     */
    public function deleteDirectory(string $path): bool;

    /**
     * Set visibility on a file.
     */
    public function setVisibility(
        string $path,
        string $visibility,
    ): bool;

    /**
     * Get visibility of a file.
     */
    public function visibility(string $path): string;
}
