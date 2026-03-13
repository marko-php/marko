# Marko Authentication

Session and token-based authentication---guards protect routes, events track activity, middleware controls access.

## Installation

```bash
composer require marko/authentication
```

## Quick Example

```php
use Marko\Authentication\AuthManager;

class DashboardController
{
    public function __construct(
        private AuthManager $authManager,
    ) {}

    public function index(): Response
    {
        if ($this->authManager->check()) {
            $user = $this->authManager->user();
            return new Response("Welcome, user {$this->authManager->id()}");
        }

        return Response::redirect('/login');
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/authentication](https://marko.build/docs/packages/authentication/)
