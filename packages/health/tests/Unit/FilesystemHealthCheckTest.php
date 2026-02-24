<?php

declare(strict_types=1);

use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Values\FileInfo;
use Marko\Health\Checks\FilesystemHealthCheck;
use Marko\Health\Value\HealthStatus;

it('checks filesystem writability via FilesystemHealthCheck', function () {
    $written = [];
    $filesystem = new class ($written) implements FilesystemInterface
    {
        public function __construct(private array &$written) {}

        public function exists(string $path): bool
        {
            return isset($this->written[$path]);
        }

        public function isFile(string $path): bool
        {
            return false;
        }

        public function isDirectory(string $path): bool
        {
            return false;
        }

        public function info(string $path): FileInfo
        {
            throw new RuntimeException('Not implemented');
        }

        public function read(string $path): string
        {
            return $this->written[$path] ?? '';
        }

        public function readStream(string $path): mixed
        {
            throw new RuntimeException('Not implemented');
        }

        public function write(string $path, string $contents, array $options = []): bool
        {
            $this->written[$path] = $contents;

            return true;
        }

        public function writeStream(string $path, mixed $resource, array $options = []): bool
        {
            return true;
        }

        public function append(string $path, string $contents): bool
        {
            return true;
        }

        public function delete(string $path): bool
        {
            unset($this->written[$path]);

            return true;
        }

        public function copy(string $source, string $destination): bool
        {
            return true;
        }

        public function move(string $source, string $destination): bool
        {
            return true;
        }

        public function size(string $path): int
        {
            return 0;
        }

        public function lastModified(string $path): int
        {
            return 0;
        }

        public function mimeType(string $path): string
        {
            return 'text/plain';
        }

        public function listDirectory(string $path = '/'): DirectoryListingInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function makeDirectory(string $path): bool
        {
            return true;
        }

        public function deleteDirectory(string $path): bool
        {
            return true;
        }

        public function setVisibility(string $path, string $visibility): bool
        {
            return true;
        }

        public function visibility(string $path): string
        {
            return 'public';
        }
    };

    $check = new FilesystemHealthCheck($filesystem);

    expect($check->getName())->toBe('filesystem');

    $result = $check->check();

    expect($result->status)->toBe(HealthStatus::Healthy)
        ->and($result->name)->toBe('filesystem');
});

it('returns unhealthy status when filesystem write fails', function () {
    $filesystem = new class () implements FilesystemInterface
    {
        public function exists(string $path): bool
        {
            return false;
        }

        public function isFile(string $path): bool
        {
            return false;
        }

        public function isDirectory(string $path): bool
        {
            return false;
        }

        public function info(string $path): FileInfo
        {
            throw new RuntimeException('Not implemented');
        }

        public function read(string $path): string
        {
            return '';
        }

        public function readStream(string $path): mixed
        {
            throw new RuntimeException('Not implemented');
        }

        public function write(string $path, string $contents, array $options = []): bool
        {
            throw new RuntimeException('Filesystem not writable');
        }

        public function writeStream(string $path, mixed $resource, array $options = []): bool
        {
            return false;
        }

        public function append(string $path, string $contents): bool
        {
            return false;
        }

        public function delete(string $path): bool
        {
            return false;
        }

        public function copy(string $source, string $destination): bool
        {
            return false;
        }

        public function move(string $source, string $destination): bool
        {
            return false;
        }

        public function size(string $path): int
        {
            return 0;
        }

        public function lastModified(string $path): int
        {
            return 0;
        }

        public function mimeType(string $path): string
        {
            return 'text/plain';
        }

        public function listDirectory(string $path = '/'): DirectoryListingInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function makeDirectory(string $path): bool
        {
            return false;
        }

        public function deleteDirectory(string $path): bool
        {
            return false;
        }

        public function setVisibility(string $path, string $visibility): bool
        {
            return false;
        }

        public function visibility(string $path): string
        {
            return 'public';
        }
    };

    $check = new FilesystemHealthCheck($filesystem);
    $result = $check->check();

    expect($result->status)->toBe(HealthStatus::Unhealthy)
        ->and($result->message)->toContain('Filesystem not writable');
});
