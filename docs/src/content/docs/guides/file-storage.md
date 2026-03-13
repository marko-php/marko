---
title: File Storage
description: Store and retrieve files with pluggable backends --- local disk, Amazon S3, or custom drivers.
---

Marko's filesystem packages provide a unified API for storing and retrieving files across different backends. Code against `FilesystemInterface` for single-disk usage, or use `FilesystemManager` to work with multiple disks simultaneously. Swap from local storage to S3 by changing configuration --- no application code changes needed.

## Setup

Install the core filesystem package along with one or more drivers:

```bash
# Local filesystem (stores files on disk)
composer require marko/filesystem marko/filesystem-local

# Amazon S3 / S3-compatible storage
composer require marko/filesystem marko/filesystem-s3
```

Configure your disks in the filesystem config file:

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

The `default` key determines which disk is used when you inject `FilesystemInterface` directly. The local driver accepts relative or absolute paths --- relative paths resolve from the project root.

## Reading and Writing Files

Inject `FilesystemInterface` for default disk operations:

```php title="app/blog/Service/DocumentService.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Filesystem\Contracts\FilesystemInterface;

class DocumentService
{
    public function __construct(
        private readonly FilesystemInterface $filesystem,
    ) {}

    public function save(string $name, string $contents): void
    {
        $this->filesystem->write("documents/$name", $contents);
    }

    public function load(string $name): string
    {
        return $this->filesystem->read("documents/$name");
    }

    public function remove(string $name): void
    {
        $this->filesystem->delete("documents/$name");
    }
}
```

### Common File Operations

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

// Write a file (creates parent directories automatically)
$filesystem->write('reports/q1.pdf', $contents);

// Write with visibility
$filesystem->write('reports/q1.pdf', $contents, ['visibility' => 'public']);

// Append to a file
$filesystem->append('logs/activity.log', $entry);

// Read file contents
$contents = $filesystem->read('reports/q1.pdf');

// Read as a stream (for large files)
$stream = $filesystem->readStream('backups/database.sql');

// Write from a stream
$filesystem->writeStream('backups/copy.sql', $stream);

// Check existence
$filesystem->exists('reports/q1.pdf');    // bool
$filesystem->isFile('reports/q1.pdf');    // bool
$filesystem->isDirectory('reports');      // bool

// Copy and move
$filesystem->copy('reports/q1.pdf', 'archive/q1.pdf');
$filesystem->move('reports/draft.pdf', 'reports/final.pdf');

// Delete a file
$filesystem->delete('reports/draft.pdf');

// File metadata
$filesystem->size('reports/q1.pdf');         // int (bytes)
$filesystem->lastModified('reports/q1.pdf'); // int (Unix timestamp)
$filesystem->mimeType('reports/q1.pdf');     // string (e.g., 'application/pdf')
```

### File Information

The `info()` method returns a `FileInfo` value object with full metadata:

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

$info = $filesystem->info('reports/q1.pdf');

$info->path;          // 'reports/q1.pdf'
$info->size;          // 524288
$info->lastModified;  // 1710288000
$info->mimeType;      // 'application/pdf'
$info->isDirectory;   // false
$info->visibility;    // 'public' or 'private'

// Convenience methods
$info->isFile();      // true
$info->isPublic();    // true if visibility is 'public'
$info->isPrivate();   // true if visibility is 'private'
$info->basename();    // 'q1.pdf'
$info->extension();   // 'pdf'
$info->directory();   // 'reports'
```

## Working with Directories

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

// Create a directory
$filesystem->makeDirectory('uploads/images');

// List directory contents
$listing = $filesystem->listDirectory('uploads');

// Get only files
foreach ($listing->files() as $entry) {
    $entry->path;         // e.g., 'uploads/photo.jpg'
    $entry->size;         // int (bytes)
    $entry->lastModified; // int (Unix timestamp)
}

// Get only subdirectories
foreach ($listing->directories() as $entry) {
    $entry->path;         // e.g., 'uploads/images'
    $entry->isDirectory;  // true
}

// Iterate over all entries
foreach ($listing->entries() as $entry) {
    if ($entry->isFile()) {
        // process file
    }
}

// Delete a directory and all its contents
$filesystem->deleteDirectory('uploads/temp');
```

## Visibility

Visibility controls who can access a file. The local driver maps visibility to Unix permissions, while the S3 driver uses ACLs:

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

// Set visibility
$filesystem->setVisibility('reports/q1.pdf', 'public');
$filesystem->setVisibility('reports/q1.pdf', 'private');

// Check visibility
$visibility = $filesystem->visibility('reports/q1.pdf'); // 'public' or 'private'
```

| Driver | Public | Private |
|---|---|---|
| Local (files) | `0644` | `0600` |
| Local (directories) | `0755` | `0700` |
| S3 | `public-read` ACL | `private` ACL |

### Public Storage Symlink

To serve files from the `public` disk via the web, create a symlink from your web root:

```bash
marko storage:link
```

This creates `public/storage` pointing to `storage/public`, making files in the public disk accessible via URL.

## Multiple Disks

Use `FilesystemManager` when you need to work with more than one disk:

```php title="app/blog/Service/MediaService.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Filesystem\Manager\FilesystemManager;

class MediaService
{
    public function __construct(
        private readonly FilesystemManager $filesystemManager,
    ) {}

    public function upload(string $path, string $contents): void
    {
        $this->filesystemManager->disk('s3')->write($path, $contents);
    }

    public function getLocalFile(string $path): string
    {
        return $this->filesystemManager->disk('local')->read($path);
    }

    public function moveToArchive(string $path): void
    {
        $contents = $this->filesystemManager->disk('s3')->read($path);
        $this->filesystemManager->disk('local')->write("archive/$path", $contents);
        $this->filesystemManager->disk('s3')->delete($path);
    }
}
```

Calling `disk()` with no argument returns the default disk --- the same one injected for `FilesystemInterface`.

## S3 URL Generation

The S3 driver provides methods for generating URLs to stored files:

```php
use Marko\Filesystem\S3\Filesystem\S3Filesystem;
use Marko\Filesystem\Manager\FilesystemManager;

/** @var S3Filesystem $s3 */
$s3 = $this->filesystemManager->disk('s3');

// Public URL
$url = $s3->url('images/photo.jpg');

// Temporary pre-signed URL (default: 1 hour)
$tempUrl = $s3->temporaryUrl('private/report.pdf', expiration: 3600);
```

URL format depends on your configuration:

- **Custom `url`** --- uses the configured base URL directly
- **Custom `endpoint` with path-style** --- uses the endpoint with the bucket in the path
- **Default** --- standard S3 format: `https://{bucket}.s3.{region}.amazonaws.com/{key}`

### S3-Compatible Services

MinIO, DigitalOcean Spaces, Cloudflare R2, and other S3-compatible services work by adding `endpoint` and `path_style_endpoint` to the config:

```php title="config/filesystem.php"
'minio' => [
    'driver' => 's3',
    'bucket' => $_ENV['MINIO_BUCKET'],
    'region' => $_ENV['MINIO_REGION'],
    'key' => $_ENV['MINIO_KEY'],
    'secret' => $_ENV['MINIO_SECRET'],
    'endpoint' => $_ENV['MINIO_ENDPOINT'],
    'path_style_endpoint' => true,
],
```

## Customization

### Swapping Backends

Change your default disk from local to S3 by updating the config:

```php title="config/filesystem.php"
return [
    'default' => 's3',
    'disks' => [
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

No application code changes are needed --- any class injecting `FilesystemInterface` automatically uses the new backend.

### Creating a Custom Driver

Build a custom filesystem driver by implementing `FilesystemDriverFactoryInterface` and marking it with the `#[FilesystemDriver]` attribute:

```php title="packages/filesystem-ftp/src/Factory/FtpFilesystemFactory.php"
<?php

declare(strict_types=1);

namespace Vendor\Filesystem\Ftp\Factory;

use Marko\Filesystem\Attributes\FilesystemDriver;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;

#[FilesystemDriver('ftp')]
class FtpFilesystemFactory implements FilesystemDriverFactoryInterface
{
    public function create(array $config): FilesystemInterface
    {
        // Build and return your FTP filesystem implementation
        return new FtpFilesystem(
            host: $config['host'],
            username: $config['username'],
            password: $config['password'],
        );
    }
}
```

The `#[FilesystemDriver('ftp')]` attribute registers the factory under the `ftp` driver name. Marko discovers it automatically from your module's `src` directory. Then configure it like any other disk:

```php title="config/filesystem.php"
'ftp' => [
    'driver' => 'ftp',
    'host' => $_ENV['FTP_HOST'],
    'username' => $_ENV['FTP_USER'],
    'password' => $_ENV['FTP_PASS'],
],
```

### Extending an Existing Driver

Use a Preference to wrap or extend an existing driver:

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
        // Log write operation before delegating
        return parent::write($path, $contents, $options);
    }
}
```

## Testing

Since `FilesystemInterface` is injected via constructor, you can provide a test double in your tests. The local driver works well for integration tests with a temporary directory:

```php
use Marko\Filesystem\Local\Filesystem\LocalFilesystem;
use Marko\Filesystem\Contracts\FilesystemInterface;

// Create a filesystem in a temp directory
$filesystem = new LocalFilesystem(
    basePath: sys_get_temp_dir() . '/test-' . uniqid(),
);

// Use it in your service
$service = new DocumentService(filesystem: $filesystem);
$service->save('test.txt', 'Hello, world!');

// Assert the file was written
expect($filesystem->exists('test.txt'))->toBeTrue();
expect($filesystem->read('test.txt'))->toBe('Hello, world!');
```

For unit tests, create a mock or stub of `FilesystemInterface`:

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

$filesystem = Mockery::mock(FilesystemInterface::class);
$filesystem->shouldReceive('write')
    ->once()
    ->with('documents/report.pdf', 'contents')
    ->andReturn(true);

$service = new DocumentService(filesystem: $filesystem);
$service->save('report.pdf', 'contents');
```

## Next Steps

- [marko/filesystem reference](/docs/packages/filesystem/) --- full API and configuration details
- [marko/filesystem-local reference](/docs/packages/filesystem-local/) --- local driver specifics
- [marko/filesystem-s3 reference](/docs/packages/filesystem-s3/) --- S3 driver, URL generation, and pre-signed URLs
- [Testing](/docs/guides/testing/) --- testing strategies and fakes
