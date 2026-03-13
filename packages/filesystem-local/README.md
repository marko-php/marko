# marko/filesystem-local

Local filesystem driver--reads and writes files on disk with path traversal protection and atomic writes.

## Installation

```bash
composer require marko/filesystem-local
```

This automatically installs `marko/filesystem`. Requires the `ext-fileinfo` PHP extension.

## Quick Example

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
}
```

## Documentation

Full usage, API reference, and examples: [marko/filesystem-local](https://marko.build/docs/packages/filesystem-local/)
