# marko/validation-database

Entity-backed Exists and Unique validation rules --- looks records up by entity class and property through marko/database.

## Installation

```bash
composer require marko/validation-database
```

Requires `marko/database` and a database driver. Installing the package binds `RecordLookupInterface`; no configuration is needed.

## Quick Example

```php
use App\Blog\Entity\Channel;
use App\Blog\Entity\User;
use Marko\Validation\Rules\RecordRules;

public function __construct(
    private readonly RecordRules $recordRules,
) {}

$rules = [
    'channel_id' => ['required', $this->recordRules->exists(Channel::class)],
    'email' => ['required', 'email', $this->recordRules->unique(User::class, 'email')->caseInsensitive()->ignore($user->id)],
];
```

## Documentation

Full usage, API reference, and examples: [marko/validation-database](https://marko.build/docs/packages/validation-database/)
