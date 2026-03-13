# marko/translation

Translation and i18n interface with placeholder replacement, pluralization, and fallback locale support.

## Installation

```bash
composer require marko/translation
```

Note: You also need an implementation package such as `marko/translation-file` to load translations from disk.

## Quick Example

```php
use Marko\Translation\Contracts\TranslatorInterface;

class WelcomeController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function greet(): string
    {
        return $this->translator->get('messages.welcome');
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/translation](https://marko.build/docs/packages/translation/)
