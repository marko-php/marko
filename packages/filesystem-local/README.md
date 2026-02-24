# Marko Filesystem Local

Local filesystem driver--reads and writes files on disk with path traversal protection and atomic writes.

## Overview

The local filesystem driver stores files on the server's disk. Writes are atomic (temp file + rename) to prevent corruption. Paths are validated against directory traversal attacks. Visibility maps to Unix file permissions (public: 0644/0755, private: 0600/0700). MIME types are detected via the `fileinfo` extension.

Implements `FilesystemInterface` from `marko/filesystem`.

## Installation

```bash
composer require marko/filesystem-local
```

This automatically installs `marko/filesystem`. Requires the `ext-fileinfo` PHP extension.

## Usage

### Configuration

Add a local disk to your filesystem config:

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
    ],
];
```

The `path` can be absolute or relative to the project root. Directories are created automatically on first write.

### How It Works

Once configured, inject `FilesystemInterface` as usual--the local driver is used automatically:

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
|------------|-------|-------------|
| `public` | 0644 | 0755 |
| `private` | 0600 | 0700 |

```php
$this->filesystem->setVisibility('reports/summary.pdf', 'private');
$visibility = $this->filesystem->visibility('reports/summary.pdf');
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

Implements all methods from `FilesystemInterface`. See `marko/filesystem` for the full contract.
