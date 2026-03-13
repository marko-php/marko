# marko/authorization

Gates, policies, and the `#[Can]` attribute -- control who can do what with expressive, testable authorization checks.

## Installation

```bash
composer require marko/authorization
```

## Quick Example

```php
use Marko\Authorization\Contracts\GateInterface;

// Define an ability
$gate->define(
    'edit-settings',
    fn (?AuthorizableInterface $user) => $user?->can('admin', true) ?? false,
);

// Check it
$gate->authorize('edit-settings');
```

## Documentation

Full usage, API reference, and examples: [marko/authorization](https://marko.build/docs/packages/authorization/)
