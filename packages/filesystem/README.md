# marko/filesystem

Interfaces for file storage--defines how files are read, written, and organized across any storage backend.

## Installation

```bash
composer require marko/filesystem
```

Note: You typically install a driver package (like `marko/filesystem-local`) which requires this automatically.

## Quick Example

```php
use Marko\Filesystem\Contracts\FilesystemInterface;

class DocumentService
{
    public function __construct(
        private FilesystemInterface $filesystem,
    ) {}

    public function saveDocument(string $name, string $contents): void
    {
        $this->filesystem->write("documents/$name", $contents);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/filesystem](https://marko.build/docs/packages/filesystem/)
