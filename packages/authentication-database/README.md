# marko/authentication-database

Database-backed password reset token storage --- reset tokens survive cache flushes and `auth:clear-reset-tokens` deletes expired rows.

## Installation

```bash
composer require marko/authentication-database
```

Requires `marko/database` and one of its drivers. Then run `marko db:migrate` to create the `password_reset_tokens` table from the entity the package ships.

## Quick Example

```php
use Marko\Authentication\Contracts\PasswordResetBrokerInterface;

public function __construct(
    private readonly PasswordResetBrokerInterface $passwordResetBroker,
) {}

public function forgotPassword(string $email): void
{
    $this->passwordResetBroker->sendResetLink(['email' => $email]);
}
```

Installing this package replaces the cache-backed `PasswordResetTokenRepositoryInterface` default of `marko/authentication`, so the broker stores tokens in the table. Schedule `marko auth:clear-reset-tokens` to delete expired ones.

## Documentation

Full usage, API reference, and examples: [marko/authentication-database](https://marko.build/docs/packages/authentication-database/)
