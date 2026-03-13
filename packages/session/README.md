# marko/session

Session interfaces and infrastructure--defines session management, flash messages, and garbage collection without coupling to a storage backend.

## Installation

```bash
composer require marko/session
```

Note: You also need a driver package. See `marko/session-file` or `marko/session-database`.

## Quick Example

```php
use Marko\Session\Contracts\SessionInterface;

public function __construct(
    private readonly SessionInterface $session,
) {}

public function handle(): void
{
    $this->session->start();
    $this->session->set('user_id', 42);
    $this->session->flash()->add('success', 'Profile updated.');
    $this->session->save();
}
```

## Documentation

Full usage, API reference, and examples: [marko/session](https://marko.build/docs/packages/session/)
