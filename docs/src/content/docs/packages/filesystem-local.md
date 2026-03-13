---
title: marko/filesystem-local
description: Local filesystem driver — reads and writes files on disk with path traversal protection and atomic writes.
---

Local filesystem driver --- reads and writes files on disk with path traversal protection and atomic writes. Stores files on the server's disk using atomic writes (temp file + rename) to prevent corruption. Paths are validated against directory traversal attacks. Visibility maps to Unix file permissions (public: 0644/0755, private: 0600/0700). MIME types are detected via the `fileinfo` extension.

Implements `FilesystemInterface` from [`marko/filesystem`](/docs/packages/filesystem/).

## Installation

```bash
composer require marko/filesystem-local
```

This automatically installs `marko/filesystem`. Requires the `ext-fileinfo` PHP extension.

## Configuration

Add a local disk to your filesystem config:

```php title="config/filesystem.php"
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
    ],
];
```

The `path` can be absolute or relative to the project root. Directories are created automatically on first write. Setting `public` to `true` makes new files world-readable by default.

## Usage

Once configured, inject `FilesystemInterface` as usual --- the local driver is used automatically:

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

class ReportService
{
    public function __construct(
        private FilesystemInterface $filesystem,
    ) {}

    public function generateReport(
        string $name,
        string $contents,
    ): void {
        $this->filesystem->write(
            "reports/$name.pdf",
            $contents,
            ['visibility' => 'private'],
        );
    }

    public function listReports(): array
    {
        $listing = $this->filesystem->listDirectory('reports');

        return $listing->files();
    }
}
```

### Visibility

Visibility controls Unix file permissions:

| Visibility | Files | Directories |
|---|---|---|
| `public` | 0644 | 0755 |
| `private` | 0600 | 0700 |

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

// Assuming $filesystem is a FilesystemInterface instance
$filesystem->setVisibility('reports/summary.pdf', 'private');
$visibility = $filesystem->visibility('reports/summary.pdf');
```

## Customization

Replace the local filesystem with a Preference for custom behavior:

```php
use Marko\Core\Attributes\Preference;
use Marko\Filesystem\Local\Filesystem\LocalFilesystem;

#[Preference(replaces: LocalFilesystem::class)]
class AuditedLocalFilesystem extends LocalFilesystem
{
    public function write(
        string $path,
        string $contents,
        array $options = [],
    ): bool {
        // Log write operation...
        return parent::write($path, $contents, $options);
    }
}
```

## API Reference

Implements all methods from `FilesystemInterface`. See [`marko/filesystem`](/docs/packages/filesystem/) for the full contract.

### Key Methods

| Method | Description |
|---|---|
| `read(string $path): string` | Read file contents |
| `readStream(string $path): mixed` | Open a file as a readable stream |
| `write(string $path, string $contents, array $options = []): bool` | Write contents atomically (temp file + rename) |
| `writeStream(string $path, mixed $resource, array $options = []): bool` | Write from a stream resource |
| `append(string $path, string $contents): bool` | Append contents to a file with `LOCK_EX` |
| `delete(string $path): bool` | Delete a file (returns `true` if already missing) |
| `copy(string $source, string $destination): bool` | Copy a file to a new location |
| `move(string $source, string $destination): bool` | Move a file to a new location |
| `exists(string $path): bool` | Check if a file or directory exists |
| `isFile(string $path): bool` | Check if the path is a file |
| `isDirectory(string $path): bool` | Check if the path is a directory |
| `size(string $path): int` | Get file size in bytes |
| `lastModified(string $path): int` | Get last modified timestamp |
| `mimeType(string $path): string` | Detect MIME type via `fileinfo` |
| `info(string $path): FileInfo` | Get full file metadata (size, MIME type, visibility, last modified) |
| `listDirectory(string $path = '/'): DirectoryListingInterface` | List directory entries |
| `makeDirectory(string $path): bool` | Create a directory recursively |
| `deleteDirectory(string $path): bool` | Delete a directory and all its contents |
| `setVisibility(string $path, string $visibility): bool` | Set file or directory permissions |
| `visibility(string $path): string` | Get current visibility (`public` or `private`) |

### Storage Details

- Writes use a temp file with `LOCK_EX` followed by an atomic `rename()` to prevent corruption.
- Paths containing `../` are rejected with a `PathException` to prevent directory traversal.
- Missing parent directories are created automatically on write, copy, and move operations.
- MIME type detection uses `finfo_open(FILEINFO_MIME_TYPE)`, falling back to `application/octet-stream`.
- Visibility is determined by checking the world-readable bit on the file's permissions.
