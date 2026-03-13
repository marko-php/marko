---
title: marko/translation
description: Translation and i18n interface with placeholder replacement, pluralization, and fallback locale support.
---

Translation and i18n interface with placeholder replacement, pluralization, and fallback locale support --- so your application speaks every language your users need. This package provides the `TranslatorInterface` and `TranslationLoaderInterface` contracts for loading and resolving translated strings. It supports dot-notation keys, namespaced translations for packages, `:placeholder` replacement, and pluralization with labeled variants (`zero`, `one`, `few`, `many`, `other`). When a key is missing for the current locale, the translator falls back automatically.

**This package defines contracts and core logic.** Install an implementation package such as `marko/translation-file` to load translations from disk.

## Installation

```bash
composer require marko/translation
```

Note: You typically install an implementation package (like `marko/translation-file`) which requires this automatically.

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

```php title="lang/en/messages.php"
return [
    'items' => 'zero:No items|one:One item|other::count items',
];
```

Supported labels: `zero`, `one`, `few` (2--4), `many` (5+), `other` (default).

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

### Configuration

The `TranslationConfig` class provides typed access to translation configuration values:

```php
use Marko\Translation\Config\TranslationConfig;

class MyService
{
    public function __construct(
        private TranslationConfig $translationConfig,
    ) {}

    public function setup(): void
    {
        $locale = $this->translationConfig->locale;
        $fallback = $this->translationConfig->fallbackLocale;
    }
}
```

## Customization

Replace the `Translator` with a custom implementation via [Preferences](/docs/packages/core/):

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
use Marko\Translation\Contracts\TranslatorInterface;

public function get(string $key, array $replacements = [], ?string $locale = null): string;
public function choice(string $key, int $count, array $replacements = [], ?string $locale = null): string;
public function setLocale(string $locale): void;
public function getLocale(): string;
```

Both `get()` and `choice()` throw `MissingTranslationException` when a key cannot be resolved in the current or fallback locale.

### TranslationLoaderInterface

```php
use Marko\Translation\Contracts\TranslationLoaderInterface;

public function load(string $locale, string $group, ?string $namespace = null): array;
```

### TranslationConfig

```php
use Marko\Translation\Config\TranslationConfig;

public string $locale;
public string $fallbackLocale;
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `TranslationException` | Base exception for all translation errors --- includes `getContext()` and `getSuggestion()` methods |
| `MissingTranslationException` | Thrown when a translation key is not found for the current or fallback locale |
