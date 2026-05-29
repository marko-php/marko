---
title: marko/filesystem-s3
description: S3 filesystem driver — stores files in Amazon S3 or any S3-compatible service with URL generation and pre-signed URLs.
---

S3 filesystem driver --- stores files in Amazon S3 or any S3-compatible service with URL generation and pre-signed URLs. Supports key prefixing, visibility via ACLs, MIME type detection, public URL generation, and temporary pre-signed URLs for private files. Works with Amazon S3, MinIO, DigitalOcean Spaces, Cloudflare R2, and any S3-compatible service. Uses the AWS SDK for PHP.

Implements `FilesystemInterface` from [`marko/filesystem`](/docs/packages/filesystem/).

## Installation

```bash
composer require marko/filesystem-s3
```

This automatically installs `marko/filesystem` and `aws/aws-sdk-php`.

## Configuration

Add an S3 disk to your filesystem config:

```php title="config/filesystem.php"
return [
    'default' => 'local',
    'disks' => [
        's3' => [
            'driver' => 's3',
            'bucket' => $_ENV['AWS_BUCKET'],
            'region' => $_ENV['AWS_DEFAULT_REGION'],
            'key' => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            'prefix' => '',
        ],
    ],
];
```

For S3-compatible services, add endpoint configuration:

```php title="config/filesystem.php"
's3' => [
    'driver' => 's3',
    'bucket' => $_ENV['S3_BUCKET'],
    'region' => $_ENV['S3_REGION'],
    'key' => $_ENV['S3_KEY'],
    'secret' => $_ENV['S3_SECRET'],
    'endpoint' => $_ENV['S3_ENDPOINT'],
    'path_style_endpoint' => true,
],
```

### S3Config

The factory builds an `S3Config` value object from your disk configuration. The following keys are supported:

| Key | Required | Default | Description |
|---|---|---|---|
| `bucket` | Yes | --- | S3 bucket name |
| `region` | Yes | --- | AWS region (e.g., `us-east-1`) |
| `key` | Yes | --- | AWS access key ID |
| `secret` | Yes | --- | AWS secret access key |
| `prefix` | No | `''` | Key prefix applied to all paths |
| `endpoint` | No | `null` | Custom endpoint URL for S3-compatible services |
| `url` | No | `null` | Custom base URL for public URL generation |
| `path_style_endpoint` | No | `false` | Use path-style URLs instead of virtual-hosted |

## Usage

Use `FilesystemManager` to access the S3 disk:

```php
use Marko\Filesystem\Manager\FilesystemManager;

class MediaService
{
    public function __construct(
        private FilesystemManager $filesystemManager,
    ) {}

    public function upload(
        string $path,
        string $contents,
    ): void {
        $this->filesystemManager->disk('s3')->write(
            $path,
            $contents,
            ['visibility' => 'public'],
        );
    }

    public function download(
        string $path,
    ): string {
        return $this->filesystemManager->disk('s3')->read($path);
    }
}
```

### URL Generation

The S3 driver provides URL generation for stored files:

```php
use Marko\Filesystem\S3\Filesystem\S3Filesystem;

/** @var S3Filesystem $s3 */
$s3 = $this->filesystemManager->disk('s3');

// Public URL
$url = $s3->url('images/photo.jpg');

// Temporary pre-signed URL (default: 1 hour)
$tempUrl = $s3->temporaryUrl('private/report.pdf', expiration: 3600);
```

Public URLs are constructed based on your configuration:

- **Custom `url`** --- uses the configured base URL directly.
- **Custom `endpoint` with path-style** --- uses the endpoint with the bucket in the path.
- **Default** --- uses the standard S3 virtual-hosted URL format: `https://{bucket}.s3.{region}.amazonaws.com/{key}`.

### Key Prefixing

All keys are automatically prefixed when a `prefix` is configured, keeping your S3 bucket organized without changing application paths:

```php
// With prefix 'uploads':
// Application path: 'images/photo.jpg'
// S3 key: 'uploads/images/photo.jpg'
```

### Visibility

Visibility is managed via S3 ACLs. Pass `'visibility'` in the options array when writing, or use `setVisibility()` to change it after the fact:

```php
use Marko\Filesystem\S3\Filesystem\S3Filesystem;

/** @var S3Filesystem $s3 */
$s3 = $this->filesystemManager->disk('s3');

// Write with public visibility
$s3->write('images/photo.jpg', $contents, ['visibility' => 'public']);

// Change visibility later
$s3->setVisibility('images/photo.jpg', 'private');

// Check current visibility
$visibility = $s3->visibility('images/photo.jpg'); // 'public' or 'private'
```

## Customization

Replace the S3 filesystem with a Preference for custom behavior:

```php
use Marko\Core\Attributes\Preference;
use Marko\Filesystem\S3\Filesystem\S3Filesystem;

#[Preference(replaces: S3Filesystem::class)]
class CdnS3Filesystem extends S3Filesystem
{
    public function url(
        string $path,
    ): string {
        // Return CDN URL instead of direct S3 URL
        return 'https://cdn.example.com/' . ltrim($path, '/');
    }
}
```

## API Reference

Implements all methods from `FilesystemInterface`. See [`marko/filesystem`](/docs/packages/filesystem/) for the full contract.

### S3-Specific Methods

| Method | Description |
|---|---|
| `url(string $path): string` | Generate a public URL for the given path |
| `temporaryUrl(string $path, int $expiration = 3600): string` | Generate a temporary pre-signed URL (default: 1 hour) |

### FilesystemInterface Methods

| Method | Description |
|---|---|
| `exists(string $path): bool` | Check if a file exists |
| `isFile(string $path): bool` | Check if the path is a file |
| `isDirectory(string $path): bool` | Check if the path is a directory |
| `info(string $path): FileInfo` | Get file metadata (size, last modified, MIME type) |
| `read(string $path): string` | Read file contents as a string |
| `readStream(string $path): mixed` | Read file contents as a stream resource |
| `write(string $path, string $contents, array $options = []): bool` | Write contents to a file |
| `writeStream(string $path, mixed $resource, array $options = []): bool` | Write a stream resource to a file |
| `append(string $path, string $contents): bool` | Append contents to a file (reads + rewrites in S3) |
| `delete(string $path): bool` | Delete a file |
| `copy(string $source, string $destination): bool` | Copy a file |
| `move(string $source, string $destination): bool` | Move a file (copy + delete) |
| `size(string $path): int` | Get the file size in bytes |
| `lastModified(string $path): int` | Get the last modified timestamp |
| `mimeType(string $path): string` | Get the MIME type |
| `listDirectory(string $path = '/'): DirectoryListingInterface` | List files and directories |
| `makeDirectory(string $path): bool` | Create a directory marker |
| `deleteDirectory(string $path): bool` | Delete a directory and all its contents |
| `setVisibility(string $path, string $visibility): bool` | Set file visibility via ACL (`public` or `private`) |
| `visibility(string $path): string` | Get the current visibility (`public` or `private`) |

### MIME Type Detection

The driver automatically detects MIME types from file extensions when writing. Over 40 common types are supported, including images, documents, audio, video, fonts, and archives. Unrecognized extensions default to `application/octet-stream`. You can override detection by passing `content_type` in the options array:

```php
$s3->write('data.bin', $contents, ['content_type' => 'application/custom']);
```
