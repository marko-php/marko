# marko/log-stream

Stream logging driver --- writes log records to stderr or stdout for containers and process supervisors.

## Installation

```bash
composer require marko/log-stream
```

## Quick Example

```bash
# Container environment: stderr only, one JSON object per line
LOG_HANDLERS=stream
LOG_FORMATTER=json
```

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

Full usage, API reference, and examples: [marko/log-stream](https://marko.build/docs/packages/log-stream/)
