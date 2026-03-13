# marko/log-file

File-based logging driver --- writes log messages to disk with daily or size-based rotation.

## Installation

```bash
composer require marko/log-file
```

## Quick Example

```php
use Marko\Log\Contracts\LoggerInterface;

class ImportService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function import(string $file): void
    {
        $this->logger->info('Starting import', ['file' => $file]);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/log-file](https://marko.build/docs/packages/log-file/)
