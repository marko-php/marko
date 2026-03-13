# marko/media

Manage file uploads and media — handles validation, storage via any filesystem driver, URL generation, and polymorphic entity attachments.

## Installation

```bash
composer require marko/media
```

## Quick Example

```php
use Marko\Media\Contracts\MediaManagerInterface;
use Marko\Media\Value\UploadedFile;

class PostController
{
    public function __construct(
        private MediaManagerInterface $mediaManager,
    ) {}

    public function uploadAvatar(): void
    {
        $file = new UploadedFile(
            name: $_FILES['avatar']['name'],
            tmpPath: $_FILES['avatar']['tmp_name'],
            mimeType: $_FILES['avatar']['type'],
            size: $_FILES['avatar']['size'],
            extension: pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION),
        );

        $media = $this->mediaManager->upload($file);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/media](https://marko.build/docs/packages/media/)
