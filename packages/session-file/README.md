# marko/session-file

File-based session driver--stores session data as files on disk with file locking for concurrent request safety.

## Installation

```bash
composer require marko/session-file
```

## Quick Example

```php
use Marko\Session\Contracts\SessionInterface;

public function __construct(
    private readonly SessionInterface $session,
) {}

public function handle(): void
{
    $this->session->set('user_id', 42);
}
```

Installing this package automatically registers the file handler, binds `SessionInterface`, and adds `SessionMiddleware` globally. No manual configuration is needed.

## Documentation

Full usage, API reference, and examples: [marko/session-file](https://marko.build/docs/packages/session-file/)
