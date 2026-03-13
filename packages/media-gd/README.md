# marko/media-gd

GD image processing for marko/media — resize, crop, and convert images using PHP's built-in GD extension with no additional system dependencies.

## Installation

```bash
composer require marko/media-gd
```

## Quick Example

```php
use Marko\Media\Contracts\ImageProcessorInterface;

class ImageService
{
    public function __construct(
        private ImageProcessorInterface $imageProcessor,
    ) {}

    public function resizeForWeb(string $imagePath): string
    {
        return $this->imageProcessor->resize(
            imagePath: $imagePath,
            width: 800,
            height: 600,
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/media-gd](https://marko.build/docs/packages/media-gd/)
