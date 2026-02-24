# Marko Translation

Translation and i18n interface with placeholder replacement, pluralization, and fallback locale support--so your application speaks every language your users need.

## Overview

Translation provides the `TranslatorInterface` and `TranslationLoaderInterface` contracts for loading and resolving translated strings. It supports dot-notation keys, namespaced translations for packages, `:placeholder` replacement, and pluralization with labeled variants (`zero`, `one`, `few`, `many`, `other`). When a key is missing for the current locale, the translator falls back automatically.

## Installation

```bash
composer require marko/translation
```

Note: You also need an implementation package such as `marko/translation-file` to load translations from disk.

## Usage

### Translating Strings

Inject `TranslatorInterface` and call `get()` with a dot-notation key:

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

### Placeholder Replacement

Pass an array of replacements for `:placeholder` tokens:

```php
$this->translator->get(
    'messages.hello',
    ['name' => 'Alice'],
);
// Translation string: "Hello, :name!" => "Hello, Alice!"
```

### Pluralization

Use `choice()` with a count to select the correct plural form:

```php
$this->translator->choice(
    'messages.items',
    $count,
    ['count' => (string) $count],
);
```

Plural strings use pipe-separated labeled forms:

```php
// In your translation file:
return [
    'items' => 'zero:No items|one:One item|other::count items',
];
```

Supported labels: `zero`, `one`, `few` (2-4), `many` (5+), `other` (default).

### Namespaced Translations

Package translations use the `namespace::group.key` format:

```php
$this->translator->get('blog::posts.title');
```

### Switching Locale

```php
$this->translator->setLocale('fr');
$greeting = $this->translator->get('messages.welcome');
```

## Customization

Replace the `Translator` with a custom implementation via Preferences:

```php
use Marko\Core\Attributes\Preference;
use Marko\Translation\Translator;

#[Preference(replaces: Translator::class)]
class MyTranslator extends Translator
{
    public function get(
        string $key,
        array $replacements = [],
        ?string $locale = null,
    ): string {
        // Custom behavior
        return parent::get($key, $replacements, $locale);
    }
}
```

## API Reference

### TranslatorInterface

```php
interface TranslatorInterface
{
    public function get(string $key, array $replacements = [], ?string $locale = null): string;
    public function choice(string $key, int $count, array $replacements = [], ?string $locale = null): string;
    public function setLocale(string $locale): void;
    public function getLocale(): string;
}
```

### TranslationLoaderInterface

```php
interface TranslationLoaderInterface
{
    public function load(string $locale, string $group, ?string $namespace = null): array;
}
```
