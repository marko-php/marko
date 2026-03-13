# Marko Core

The foundation of Marko---provides dependency injection, modules, plugins, events, and preferences so you can extend any class without modifying its source.

## Installation

```bash
composer require marko/core
```

Most applications install this via `marko/framework`.

## Quick Example

```php
use Marko\Core\Attributes\Preference;

#[Preference(replaces: OriginalService::class)]
class MyService extends OriginalService
{
    public function doSomething(): string
    {
        return 'custom behavior';
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/core](https://marko.build/docs/packages/core/)
