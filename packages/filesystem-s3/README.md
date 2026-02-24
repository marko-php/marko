# Marko Filesystem S3

S3 filesystem driver--stores files in Amazon S3 or any S3-compatible service with URL generation and pre-signed URLs.

## Overview

The S3 filesystem driver stores files in Amazon S3 (or compatible services like MinIO, DigitalOcean Spaces, Cloudflare R2). Supports key prefixing, visibility via ACLs, MIME type detection, public URL generation, and temporary pre-signed URLs for private files. Uses the AWS SDK for PHP.

Implements `FilesystemInterface` from `marko/filesystem`.

## Installation

```bash
composer require marko/filesystem-s3
```

This automatically installs `marko/filesystem` and `aws/aws-sdk-php`.

## Usage

### Configuration

Add an S3 disk to your filesystem config:

```php
// config/filesystem.php
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

```php
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

### How It Works

Use `FilesystemManager` to access the S3 disk:

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
        $this->manager->disk('s3')->write(
            $path,
            $contents,
            ['visibility' => 'public'],
        );
    }

    public function download(
        string $path,
    ): string {
        return $this->manager->disk('s3')->read($path);
    }
}
```

### URL Generation

The S3 driver provides URL generation for stored files:

```php
use Marko\Filesystem\S3\Filesystem\S3Filesystem;

/** @var S3Filesystem $s3 */
$s3 = $this->manager->disk('s3');

// Public URL
$url = $s3->url('images/photo.jpg');

// Temporary pre-signed URL (default: 1 hour)
$tempUrl = $s3->temporaryUrl('private/report.pdf', expiration: 3600);
```

### Key Prefixing

All keys are automatically prefixed when a `prefix` is configured, keeping your S3 bucket organized without changing application paths:

```php
// With prefix 'uploads':
// Application path: 'images/photo.jpg'
// S3 key: 'uploads/images/photo.jpg'
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

Implements all methods from `FilesystemInterface`, plus S3-specific methods:

```php
public function url(string $path): string;
public function temporaryUrl(string $path, int $expiration = 3600): string;
```

See `marko/filesystem` for the full `FilesystemInterface` contract.
