---
title: marko/filesystem
description: Interfaces for file storage — defines how files are read, written, and organized across any storage backend.
---

Interfaces for file storage --- defines how files are read, written, and organized across any storage backend. Filesystem provides the contracts and infrastructure for Marko's file storage system. Type-hint against `FilesystemInterface` for single-disk usage, or `FilesystemManagerInterface` to work with multiple disks (local, S3, etc.) simultaneously. Includes the path rules every driver shares (`PathNormalizer`), driver discovery via the `#[FilesystemDriver]` attribute, a `storage:link` CLI command, and value objects for file metadata.

**This package defines contracts only.** Install a driver for implementation:

- [`marko/filesystem-local`](/docs/packages/filesystem-local/) --- Local disk storage
- [`marko/filesystem-s3`](/docs/packages/filesystem-s3/) --- Amazon S3 / S3-compatible storage

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

Inject `FilesystemManagerInterface` to access different storage backends. It is bound to `FilesystemManager`:

```php
use Marko\Filesystem\Contracts\FilesystemManagerInterface;

class MediaService
{
    public function __construct(
        private FilesystemManagerInterface $filesystemManager,
    ) {}

    public function upload(
        string $path,
        string $contents,
    ): void {
        // Write to the configured S3 disk
        $this->filesystemManager->disk('s3')->write($path, $contents);
    }

    public function getLocalFile(
        string $path,
    ): string {
        // Read from local disk
        return $this->filesystemManager->disk('local')->read($path);
    }
}
```

Calling `disk()` with no argument returns the default disk. The default disk is built once and cached under its configured name, so `disk()` and `disk('local')` return the same instance when `local` is the default.

### Replacing a Disk

`useDisk()` puts a disk instance in place for a disk name for the rest of the process, whether or not that disk was already built. Pass `null` to replace the default disk; `FilesystemInterface` forwards to the default disk, so it then resolves to the replacement too:

```php
use Marko\Filesystem\Contracts\FilesystemManagerInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeFilesystem;

$fake = new FakeFilesystem(clock: new FakeClock(), public: false);

$container->get(FilesystemManagerInterface::class)->useDisk('s3', $fake);
```

This is how tests put [`FakeFilesystem`](/docs/packages/testing/#fakefilesystem) in front of code that writes to a named disk. `useDisk(null, ...)` and `useDisk('<default disk name>', ...)` replace the same entry.

### Path Rules

Every driver normalizes paths with the same `PathNormalizer`:

- Paths are relative to the disk root. Empty and `.` segments are dropped, so `/docs//a.txt` and `./docs/a.txt` both mean `docs/a.txt`, and `''`, `/` and `.` mean the root.
- A `..` segment throws `PathException::traversalAttempt()`. `..` inside a name (`notes..txt`) is allowed.
- A NUL byte throws `PathException::invalidPath()`.
- Backslashes follow the driver's `BackslashPolicy`: the local driver translates them into `/` (`BackslashPolicy::Translate`), the S3 driver rejects them (`BackslashPolicy::Reject`).
- `deleteDirectory()` refuses the disk root with `PathException::rootDeletion()` (`normalizeForDeletion()`).

A custom driver builds the normalizer with the policy it needs and calls it on every path:

```php
use Marko\Filesystem\Path\BackslashPolicy;
use Marko\Filesystem\Path\PathNormalizer;

$normalizer = new PathNormalizer(BackslashPolicy::Reject);

$normalizer->normalize('/docs/./a.txt');      // 'docs/a.txt'
$normalizer->normalizeForDeletion('uploads'); // 'uploads'
$normalizer->normalizeForDeletion('/');       // throws PathException
```

### Configuration

Configure disks in your config file:

```php title="config/filesystem.php"
use Marko\Config\Env;

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
            'bucket' => Env::string('AWS_BUCKET', ''),
            'region' => Env::string('AWS_DEFAULT_REGION', ''),
            'key' => Env::string('AWS_ACCESS_KEY_ID', ''),
            'secret' => Env::string('AWS_SECRET_ACCESS_KEY', ''),
        ],
    ],
];
```

The `FilesystemConfig` class provides typed access to filesystem configuration values:

```php
use Marko\Filesystem\Config\FilesystemConfig;

class MyService
{
    public function __construct(
        private FilesystemConfig $filesystemConfig,
    ) {}

    public function setup(): void
    {
        $defaultDisk = $this->filesystemConfig->defaultDisk();
        $diskConfig = $this->filesystemConfig->getDisk('local');
    }
}
```

### File Information

Get metadata about files using the `FileInfo` value object:

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

$info = $this->filesystem->info('documents/report.pdf');
$info->path;          // Relative path
$info->size;          // Bytes
$info->lastModified;  // Unix timestamp
$info->mimeType;      // e.g., 'application/pdf'
$info->isDirectory;   // false
$info->visibility;    // 'public' or 'private'
```

`FileInfo` also provides convenience methods:

```php
$info->isFile();      // true if not a directory
$info->isPublic();    // true if visibility is 'public'
$info->isPrivate();   // true if visibility is 'private'
$info->basename();    // 'report.pdf'
$info->extension();   // 'pdf'
$info->directory();   // 'documents'
```

### Directory Listing

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

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
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Values\FileInfo;
use Marko\Filesystem\Contracts\DirectoryListingInterface;

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

### FilesystemManagerInterface

Bound to `FilesystemManager`.

```php
use Marko\Filesystem\Contracts\FilesystemManagerInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;

public function disk(?string $name = null): FilesystemInterface;
public function useDisk(?string $name, FilesystemInterface $disk): void;
```

### PathNormalizer

```php
use Marko\Filesystem\Path\BackslashPolicy;
use Marko\Filesystem\Path\PathNormalizer;

public function __construct(BackslashPolicy $backslashes);
public function normalize(string $path): string;
public function normalizeForDeletion(string $path): string;
```

`BackslashPolicy` has two cases: `Translate` and `Reject`.

### FilesystemDriverFactoryInterface

```php
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;

public function create(array $config): FilesystemInterface;
```

### FilesystemConfig

```php
use Marko\Filesystem\Config\FilesystemConfig;

public function defaultDisk(): string;
public function getDisk(string $name): array;
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `FilesystemException` | Base exception for all filesystem errors --- includes `getContext()` and `getSuggestion()` methods |
| `FileNotFoundException` | Thrown when a requested file does not exist |
| `PathException` | Thrown for path traversal attempts, invalid paths, paths that escape the disk root via symlinks, or attempts to delete the disk root |
| `PermissionException` | Thrown when read, write, or delete permissions are insufficient |
