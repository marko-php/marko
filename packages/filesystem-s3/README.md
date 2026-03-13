# marko/filesystem-s3

S3 filesystem driver — stores files in Amazon S3 or any S3-compatible service with URL generation and pre-signed URLs.

## Installation

```bash
composer require marko/filesystem-s3
```

This automatically installs `marko/filesystem` and `aws/aws-sdk-php`.

## Quick Example

```php
use Marko\Filesystem\Manager\FilesystemManager;

class MediaService
{
    public function __construct(
        private FilesystemManager $filesystemManager,
    ) {}

    public function upload(string $path, string $contents): void
    {
        $this->filesystemManager->disk('s3')->write($path, $contents);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/filesystem-s3](https://marko.build/docs/packages/filesystem-s3/)
