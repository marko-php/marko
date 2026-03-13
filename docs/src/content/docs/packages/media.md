---
title: marko/media
description: Manage file uploads, storage, URL generation, and polymorphic entity attachments.
---

Manage file uploads and media --- handles validation, storage via any filesystem driver, URL generation, and polymorphic entity attachments. `marko/media` accepts uploaded files, validates them against configurable size and type constraints, writes them to a filesystem disk, and persists a `Media` entity to the database. URL generation turns stored paths into public URLs, and `AttachmentManager` associates any number of media items with any entity type via a polymorphic join table. Image processing (resize, crop, convert) is available by installing a driver package.

## Installation

```bash
composer require marko/media
```

### Database Tables

Create the required tables in your database:

```sql
CREATE TABLE media (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename       VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type      VARCHAR(100) NOT NULL,
    size           INT UNSIGNED NOT NULL,
    disk           VARCHAR(50)  NOT NULL,
    path           VARCHAR(1000) NOT NULL,
    metadata       TEXT,
    created_at     DATETIME,
    updated_at     DATETIME
);

CREATE TABLE media_attachments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id        INT UNSIGNED NOT NULL,
    attachable_type VARCHAR(255) NOT NULL,
    attachable_id   VARCHAR(255) NOT NULL
);
```

## Usage

### Configuration

Publish the default configuration to `config/media.php`:

```php title="config/media.php"
<?php

declare(strict_types=1);

return [
    'disk'              => 'local',
    'max_file_size'     => 10485760,   // 10 MB in bytes
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],
    'allowed_extensions' => [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
    ],
    'url_prefix'        => '/storage',
];
```

The `MediaConfig` class provides typed access to these values:

```php
use Marko\Media\Config\MediaConfig;

class MyService
{
    public function __construct(
        private MediaConfig $mediaConfig,
    ) {}

    public function setup(): void
    {
        $disk = $this->mediaConfig->disk();
        $maxSize = $this->mediaConfig->maxFileSize();
        $mimeTypes = $this->mediaConfig->allowedMimeTypes();
        $extensions = $this->mediaConfig->allowedExtensions();
        $prefix = $this->mediaConfig->urlPrefix();
    }
}
```

### Uploading a File

Build an `UploadedFile` value object from the PHP `$_FILES` superglobal and pass it to `MediaManager::upload()`:

```php
use Marko\Media\Contracts\MediaManagerInterface;
use Marko\Media\Value\UploadedFile;
use Marko\Media\Exceptions\UploadException;

class PostController
{
    public function __construct(
        private MediaManagerInterface $mediaManager,
    ) {}

    public function uploadAvatar(): void
    {
        $raw = $_FILES['avatar'];

        $file = new UploadedFile(
            name: $raw['name'],
            tmpPath: $raw['tmp_name'],
            mimeType: $raw['type'],
            size: $raw['size'],
            extension: pathinfo($raw['name'], PATHINFO_EXTENSION),
        );

        try {
            $media = $this->mediaManager->upload($file);
            // $media->id, $media->path, $media->mimeType etc. are now set
        } catch (UploadException $e) {
            // Validation failed: file too large, wrong type, or wrong extension
        }
    }
}
```

`upload()` validates size, MIME type, and extension against config, writes the file to the configured disk under a `YYYY/MM/<unique>.<ext>` path, and returns a persisted `Media` entity.

### Generating a Public URL

```php
use Marko\Media\Contracts\UrlGeneratorInterface;

class PostController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function show(
        int $id,
    ): void {
        $media = /* retrieve Media entity */;

        $url = $this->urlGenerator->url($media);
        // Returns: /storage/2025/06/abc123.jpg
    }
}
```

The URL is `url_prefix` + `/` + `media->path`. Change `url_prefix` in config to match your web server's static file root.

### Attaching Media to an Entity

`AttachmentManager` provides a polymorphic join so any entity type can own media without schema changes:

```php
use Marko\Media\Contracts\AttachmentInterface;
use Marko\Media\Entity\Media;

class PostService
{
    public function __construct(
        private AttachmentInterface $attachmentManager,
    ) {}

    public function addFeaturedImage(
        Post $post,
        Media $media,
    ): void {
        $this->attachmentManager->attach(
            media: $media,
            attachableType: Post::class,
            attachableId: $post->id,
        );
    }

    public function removeFeaturedImage(
        Post $post,
        Media $media,
    ): void {
        $this->attachmentManager->detach(
            media: $media,
            attachableType: Post::class,
            attachableId: $post->id,
        );
    }

    /** @return array<Media> */
    public function getImages(
        Post $post,
    ): array {
        return $this->attachmentManager->findByAttachable(
            attachableType: Post::class,
            attachableId: $post->id,
        );
    }
}
```

### Retrieving and Deleting Files

```php
use Marko\Media\Contracts\MediaManagerInterface;

// Get the raw file contents
$contents = $this->mediaManager->retrieve($media);

// Check existence without fetching
$exists = $this->mediaManager->exists($media);

// Delete the file from disk and the Media record from the database
$this->mediaManager->delete($media);
```

### Image Processing

Install a driver package to enable resize, crop, and format conversion:

```bash
# GD extension (built into most PHP distributions)
composer require marko/media-gd

# Imagick extension (higher quality, more formats)
composer require marko/media-imagick
```

Once installed, the driver is automatically wired as the `ImageProcessorInterface` implementation:

```php
use Marko\Media\Contracts\ImageProcessorInterface;

class ThumbnailService
{
    public function __construct(
        private ImageProcessorInterface $imageProcessor,
    ) {}

    public function makeThumbnail(
        string $sourcePath,
    ): string {
        // Returns path to the resized image
        return $this->imageProcessor->resize(
            imagePath: $sourcePath,
            width: 300,
            height: 300,
            maintainAspect: true,
        );
    }

    public function convertToWebp(
        string $sourcePath,
    ): string {
        return $this->imageProcessor->convert(
            imagePath: $sourcePath,
            format: 'webp',
        );
    }
}
```

## Customization

### Custom Storage Backend

Switch to a different [filesystem](/docs/packages/filesystem/) disk (S3, SFTP, etc.) by changing `disk` in `config/media.php` and wiring the corresponding `FilesystemInterface` implementation:

```php title="config/media.php"
return [
    'disk' => 's3',
    // ...
];
```

```php
use Marko\Core\Attributes\Preference;
use Marko\Filesystem\Contracts\FilesystemInterface;

#[Preference(replaces: FilesystemInterface::class)]
class S3Filesystem implements FilesystemInterface
{
    // Route reads/writes through AWS S3
}
```

### Custom URL Generation

Override the URL format by replacing `UrlGenerator` via a [Preference](/docs/packages/core/):

```php
use Marko\Core\Attributes\Preference;
use Marko\Media\Contracts\UrlGeneratorInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Service\UrlGenerator;

#[Preference(replaces: UrlGenerator::class)]
class CdnUrlGenerator extends UrlGenerator implements UrlGeneratorInterface
{
    public function url(
        Media $media,
    ): string {
        return 'https://cdn.example.com/' . $media->path;
    }
}
```

### Custom Image Processor

Implement `ImageProcessorInterface` and register via [Preference](/docs/packages/core/) instead of installing a driver package:

```php
use Marko\Core\Attributes\Preference;
use Marko\Media\Contracts\ImageProcessorInterface;

#[Preference(replaces: ImageProcessorInterface::class)]
class VipsImageProcessor implements ImageProcessorInterface
{
    public function resize(
        string $imagePath,
        int $width,
        int $height,
        bool $maintainAspect = true,
    ): string {
        // libvips implementation
    }

    public function crop(
        string $imagePath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): string {
        // libvips implementation
    }

    public function convert(
        string $imagePath,
        string $format,
    ): string {
        // libvips implementation
    }
}
```

## API Reference

### UploadedFile

```php
use Marko\Media\Value\UploadedFile;

readonly class UploadedFile
{
    public function __construct(
        public string $name,
        public string $tmpPath,
        public string $mimeType,
        public int $size,
        public string $extension,
    );
}
```

### MediaManagerInterface

```php
use Marko\Media\Contracts\MediaManagerInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Value\UploadedFile;

// Validate, store, and persist a Media entity. Throws UploadException on failure.
public function upload(UploadedFile $file): Media;

// Read raw file contents from disk.
public function retrieve(Media $media): string;

// Delete the file from disk and the Media record from the database.
public function delete(Media $media): void;

// Check whether the file exists on disk.
public function exists(Media $media): bool;
```

### UrlGeneratorInterface

```php
use Marko\Media\Contracts\UrlGeneratorInterface;
use Marko\Media\Entity\Media;

// Returns url_prefix/path for the given Media entity.
public function url(Media $media): string;
```

### AttachmentInterface

```php
use Marko\Media\Contracts\AttachmentInterface;
use Marko\Media\Entity\Media;

// Associate a Media entity with any entity type.
public function attach(Media $media, string $attachableType, int|string $attachableId): void;

// Dissociate a Media entity from an entity.
public function detach(Media $media, string $attachableType, int|string $attachableId): void;

// Return all Media entities attached to an entity.
/** @return array<Media> */
public function findByAttachable(string $attachableType, int|string $attachableId): array;
```

### MediaRepositoryInterface

```php
use Marko\Media\Contracts\MediaRepositoryInterface;
use Marko\Media\Entity\Media;

public function save(Media $media): Media;
public function delete(int $id): void;
public function find(int $id): ?Media;
```

### MediaAttachmentRepositoryInterface

```php
use Marko\Media\Contracts\MediaAttachmentRepositoryInterface;

public function attach(int $mediaId, string $attachableType, int|string $attachableId): void;
public function detach(int $mediaId, string $attachableType, int|string $attachableId): void;
/** @return array<int> */
public function findByAttachable(string $attachableType, int|string $attachableId): array;
```

### ImageProcessorInterface

```php
use Marko\Media\Contracts\ImageProcessorInterface;

// Resize an image, optionally preserving the aspect ratio.
public function resize(string $imagePath, int $width, int $height, bool $maintainAspect = true): string;

// Crop an image at the given coordinates.
public function crop(string $imagePath, int $x, int $y, int $width, int $height): string;

// Convert an image to a different format (e.g. 'webp', 'png').
public function convert(string $imagePath, string $format): string;
```

### MediaConfig

```php
use Marko\Media\Config\MediaConfig;

public function disk(): string;
public function maxFileSize(): int;
public function allowedMimeTypes(): array;
public function allowedExtensions(): array;
public function urlPrefix(): string;
```

### Media Entity

```php
use Marko\Media\Entity\Media;

class Media extends Entity
{
    public ?int $id;
    public string $filename;        // Storage filename (unique)
    public string $originalFilename; // Original uploaded name
    public string $mimeType;
    public int $size;               // Bytes
    public string $disk;            // Filesystem disk name
    public string $path;            // Relative path within disk (e.g. 2025/06/abc123.jpg)
    public ?string $metadata;       // JSON metadata, nullable
    public ?string $createdAt;
    public ?string $updatedAt;
}
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `MediaException` | Base exception for all media errors |
| `UploadException` | Thrown by `MediaManager::upload()` for validation failures --- file too large, invalid MIME type, or invalid extension |
| `FileNotFoundException` | Thrown when a stored file cannot be located on disk |

## Available Image Processing Drivers

- **marko/media-gd** --- Uses PHP's built-in GD extension, no additional system libraries required
- **marko/media-imagick** --- Uses the Imagick extension, supports more formats and higher quality transforms
