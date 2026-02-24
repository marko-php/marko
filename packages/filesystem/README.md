# Marko Filesystem

Interfaces for file storage--defines how files are read, written, and organized across any storage backend.

## Overview

Filesystem provides the contracts and infrastructure for Marko's file storage system. Type-hint against `FilesystemInterface` for single-disk usage, or use `FilesystemManager` to work with multiple disks (local, S3, etc.) simultaneously. Includes driver discovery via the `#[FilesystemDriver]` attribute, a `storage:link` CLI command, and value objects for file metadata.

**This package defines contracts only.** Install a driver for implementation:

- `marko/filesystem-local` -- Local disk storage
- `marko/filesystem-s3` -- Amazon S3 / S3-compatible storage

## Installation

```bash
composer require marko/filesystem
```

Note: You typically install a driver package (like `marko/filesystem-local`) which requires this automatically.

## Usage

### Type-Hinting the Filesystem

Inject `FilesystemInterface` for default disk operations:

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

class DocumentService
{
    public function __construct(
        private FilesystemInterface $filesystem,
    ) {}

    public function saveDocument(
        string $name,
        string $contents,
    ): void {
        $this->filesystem->write("documents/$name", $contents);
    }

    public function getDocument(
        string $name,
    ): string {
        return $this->filesystem->read("documents/$name");
    }
}
```

### Working with Multiple Disks

Use `FilesystemManager` to access different storage backends:

```php
use Marko\Filesystem\Manager\FilesystemManager;

class MediaService
{
    public function __construct(
        private FilesystemManager $manager,
    ) {}

    public function upload(
        string $path,
        string $contents,
    ): void {
        // Write to the configured S3 disk
        $this->manager->disk('s3')->write($path, $contents);
    }

    public function getLocalFile(
        string $path,
    ): string {
        // Read from local disk
        return $this->manager->disk('local')->read($path);
    }
}
```

### Configuration

Configure disks in your config file:

```php
// config/filesystem.php
return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => 'storage/app',
        ],
        'public' => [
            'driver' => 'local',
            'path' => 'storage/public',
            'public' => true,
        ],
        's3' => [
            'driver' => 's3',
            'bucket' => $_ENV['AWS_BUCKET'],
            'region' => $_ENV['AWS_DEFAULT_REGION'],
            'key' => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ],
    ],
];
```

### File Information

Get metadata about files:

```php
$info = $this->filesystem->info('documents/report.pdf');
$info->size;          // Bytes
$info->lastModified;  // Unix timestamp
$info->mimeType;      // e.g., 'application/pdf'
$info->isDirectory;   // false
$info->visibility;    // 'public' or 'private'
```

### Directory Listing

```php
$listing = $this->filesystem->listDirectory('uploads');

foreach ($listing->files() as $entry) {
    $entry->path;         // Relative path
    $entry->size;         // Bytes
    $entry->lastModified; // Unix timestamp
}

foreach ($listing->directories() as $entry) {
    $entry->path;
}
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `marko storage:link` | Create `public/storage` symlink to `storage/public` |

## API Reference

### FilesystemInterface

```php
public function exists(string $path): bool;
public function isFile(string $path): bool;
public function isDirectory(string $path): bool;
public function info(string $path): FileInfo;
public function read(string $path): string;
public function readStream(string $path): mixed;
public function write(string $path, string $contents, array $options = []): bool;
public function writeStream(string $path, mixed $resource, array $options = []): bool;
public function append(string $path, string $contents): bool;
public function delete(string $path): bool;
public function copy(string $source, string $destination): bool;
public function move(string $source, string $destination): bool;
public function size(string $path): int;
public function lastModified(string $path): int;
public function mimeType(string $path): string;
public function listDirectory(string $path = '/'): DirectoryListingInterface;
public function makeDirectory(string $path): bool;
public function deleteDirectory(string $path): bool;
public function setVisibility(string $path, string $visibility): bool;
public function visibility(string $path): string;
```

### FilesystemManager

```php
public function disk(?string $name = null): FilesystemInterface;
```

### FilesystemDriverFactoryInterface

```php
public function create(array $config): FilesystemInterface;
```
