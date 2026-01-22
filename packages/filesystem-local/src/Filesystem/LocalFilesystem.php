<?php

declare(strict_types=1);

namespace Marko\Filesystem\Local\Filesystem;

use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FileNotFoundException;
use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Exceptions\PathException;
use Marko\Filesystem\Exceptions\PermissionException;
use Marko\Filesystem\Values\DirectoryEntry;
use Marko\Filesystem\Values\DirectoryListing;
use Marko\Filesystem\Values\FileInfo;
use Throwable;

class LocalFilesystem implements FilesystemInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly bool $isPublic = false,
    ) {}

    public function exists(
        string $path,
    ): bool {
        return file_exists($this->fullPath($path));
    }

    public function isFile(
        string $path,
    ): bool {
        return is_file($this->fullPath($path));
    }

    public function isDirectory(
        string $path,
    ): bool {
        return is_dir($this->fullPath($path));
    }

    public function info(
        string $path,
    ): FileInfo {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        $isDirectory = is_dir($fullPath);
        $size = $isDirectory ? 0 : (filesize($fullPath) ?: 0);
        $lastModified = filemtime($fullPath) ?: 0;
        $mimeType = $isDirectory ? 'directory' : ($this->detectMimeType($fullPath) ?? 'application/octet-stream');

        return new FileInfo(
            path: $path,
            size: $size,
            lastModified: $lastModified,
            mimeType: $mimeType,
            isDirectory: $isDirectory,
            visibility: $this->determineVisibility($fullPath),
        );
    }

    public function read(
        string $path,
    ): string {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        if (!is_readable($fullPath)) {
            throw PermissionException::cannotRead($path);
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new FilesystemException(
                message: "Failed to read file: '$path'",
                context: 'file_get_contents returned false',
                suggestion: 'Check file permissions and ensure the file is readable',
            );
        }

        return $contents;
    }

    public function readStream(
        string $path,
    ): mixed {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        if (!is_readable($fullPath)) {
            throw PermissionException::cannotRead($path);
        }

        $stream = fopen($fullPath, 'rb');

        if ($stream === false) {
            throw new FilesystemException(
                message: "Failed to open file stream: '$path'",
                context: 'fopen returned false',
                suggestion: 'Check file permissions and ensure the file is readable',
            );
        }

        return $stream;
    }

    public function write(
        string $path,
        string $contents,
        array $options = [],
    ): bool {
        $fullPath = $this->fullPath($path);
        $tempPath = $fullPath . '.tmp.' . uniqid();

        try {
            $this->ensureDirectoryExists(dirname($fullPath));

            if (file_put_contents($tempPath, $contents, LOCK_EX) === false) {
                throw PermissionException::cannotWrite($path);
            }

            if (!rename($tempPath, $fullPath)) {
                @unlink($tempPath);

                throw new FilesystemException(
                    message: "Failed to move file to final location: '$path'",
                    context: "From: $tempPath, To: $fullPath",
                    suggestion: 'Check write permissions for the target directory',
                );
            }

            if (isset($options['visibility'])) {
                $this->setVisibility($path, $options['visibility']);
            }

            return true;
        } catch (Throwable $e) {
            @unlink($tempPath);

            throw $e instanceof FilesystemException ? $e : FilesystemException::fromThrowable($e);
        }
    }

    public function writeStream(
        string $path,
        mixed $resource,
        array $options = [],
    ): bool {
        $contents = stream_get_contents($resource);

        if ($contents === false) {
            throw new FilesystemException(
                message: 'Failed to read from stream',
                context: 'stream_get_contents returned false',
                suggestion: 'Ensure the stream is readable and positioned correctly',
            );
        }

        return $this->write($path, $contents, $options);
    }

    public function append(
        string $path,
        string $contents,
    ): bool {
        $fullPath = $this->fullPath($path);

        $this->ensureDirectoryExists(dirname($fullPath));

        $result = file_put_contents($fullPath, $contents, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw PermissionException::cannotWrite($path);
        }

        return true;
    }

    public function delete(
        string $path,
    ): bool {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return true;
        }

        if (!is_writable(dirname($fullPath))) {
            throw PermissionException::cannotDelete($path);
        }

        return unlink($fullPath);
    }

    public function copy(
        string $source,
        string $destination,
    ): bool {
        $sourcePath = $this->fullPath($source);
        $destinationPath = $this->fullPath($destination);

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($source);
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        return copy($sourcePath, $destinationPath);
    }

    public function move(
        string $source,
        string $destination,
    ): bool {
        $sourcePath = $this->fullPath($source);
        $destinationPath = $this->fullPath($destination);

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($source);
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        return rename($sourcePath, $destinationPath);
    }

    public function size(
        string $path,
    ): int {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        $size = filesize($fullPath);

        return $size !== false ? $size : 0;
    }

    public function lastModified(
        string $path,
    ): int {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        $time = filemtime($fullPath);

        return $time !== false ? $time : 0;
    }

    public function mimeType(
        string $path,
    ): string {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        return $this->detectMimeType($fullPath) ?? 'application/octet-stream';
    }

    public function listDirectory(
        string $path = '/',
    ): DirectoryListingInterface {
        $fullPath = $this->fullPath($path);

        if (!is_dir($fullPath)) {
            if (!file_exists($fullPath)) {
                throw FileNotFoundException::forPath($path);
            }

            throw new FilesystemException(
                message: "Path is not a directory: '$path'",
                context: 'listDirectory called on a file',
                suggestion: 'Use listDirectory only on directories',
            );
        }

        $entries = [];
        $items = scandir($fullPath);

        if ($items === false) {
            throw new FilesystemException(
                message: "Failed to list directory: '$path'",
                context: 'scandir returned false',
                suggestion: 'Check directory permissions',
            );
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = rtrim($path, '/') . '/' . $item;
            $itemFullPath = $fullPath . '/' . $item;

            $entries[] = new DirectoryEntry(
                path: ltrim($itemPath, '/'),
                isDirectory: is_dir($itemFullPath),
                size: is_file($itemFullPath) ? (filesize($itemFullPath) ?: 0) : 0,
                lastModified: filemtime($itemFullPath) ?: 0,
            );
        }

        return new DirectoryListing($entries);
    }

    public function makeDirectory(
        string $path,
    ): bool {
        $fullPath = $this->fullPath($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, 0755, true);
    }

    public function deleteDirectory(
        string $path,
    ): bool {
        $fullPath = $this->fullPath($path);

        if (!is_dir($fullPath)) {
            return true;
        }

        return $this->deleteDirectoryRecursively($fullPath);
    }

    public function setVisibility(
        string $path,
        string $visibility,
    ): bool {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        $permissions = $visibility === 'public'
            ? (is_dir($fullPath) ? 0755 : 0644)
            : (is_dir($fullPath) ? 0700 : 0600);

        return chmod($fullPath, $permissions);
    }

    public function visibility(
        string $path,
    ): string {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw FileNotFoundException::forPath($path);
        }

        return $this->determineVisibility($fullPath);
    }

    private function validatePath(
        string $path,
    ): string {
        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        if (str_contains($normalized, '../') || str_contains($normalized, '..\\') || $normalized === '..') {
            throw PathException::traversalAttempt($path);
        }

        return $normalized;
    }

    private function fullPath(
        string $path,
    ): string {
        $normalized = $this->validatePath($path);

        if ($normalized === '' || $normalized === '.') {
            return $this->basePath;
        }

        return $this->basePath . '/' . $normalized;
    }

    private function ensureDirectoryExists(
        string $directory,
    ): void {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw PermissionException::cannotWrite($directory);
        }
    }

    private function detectMimeType(
        string $fullPath,
    ): ?string {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mimeType = finfo_file($finfo, $fullPath);

        return $mimeType !== false ? $mimeType : null;
    }

    private function determineVisibility(
        string $fullPath,
    ): string {
        $permissions = fileperms($fullPath);

        if ($permissions === false) {
            return 'private';
        }

        $worldReadable = ($permissions & 0004) !== 0;

        return $worldReadable ? 'public' : 'private';
    }

    private function deleteDirectoryRecursively(
        string $directory,
    ): bool {
        $items = scandir($directory);

        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursively($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }
}
