# Marko Translation File

File-based translation loader--reads translation arrays from PHP files organized by locale and group.

## Overview

Translation File implements `TranslationLoaderInterface` by loading translations from PHP files on disk. Files are organized as `lang/{locale}/{group}.php` and return associative arrays. Results are cached in memory after first load. Package translations are supported via registered namespaces.

## Installation

```bash
composer require marko/translation-file
```

This package provides the file-based implementation for `marko/translation`.

## Usage

### Translation File Structure

Place translation files under `lang/` in your module:

```
mymodule/
  lang/
    en/
      messages.php
      validation.php
    fr/
      messages.php
```

Each file returns an array of key-value pairs:

```php
// lang/en/messages.php
return [
    'welcome' => 'Welcome to our site!',
    'hello' => 'Hello, :name!',
    'items' => 'zero:No items|one:One item|other::count items',
];
```

Nested keys are supported:

```php
return [
    'auth' => [
        'login' => 'Log in',
        'logout' => 'Log out',
    ],
];
// Access via: $translator->get('messages.auth.login')
```

### Registering Package Namespaces

Packages register their own translation directory so keys use the `namespace::group.key` format:

```php
use Marko\Translation\File\Loader\FileTranslationLoader;

$loader->addNamespace(
    'blog',
    '/path/to/blog/lang',
);

// Now accessible as: $translator->get('blog::posts.title')
```

### For Module Developers

You do not need to interact with the loader directly. Place your translation files in the `lang/` directory and use `TranslatorInterface` to retrieve them:

```php
use Marko\Translation\Contracts\TranslatorInterface;

class PostController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function index(): string
    {
        return $this->translator->get('posts.page_title');
    }
}
```

## API Reference

### FileTranslationLoader

```php
class FileTranslationLoader implements TranslationLoaderInterface
{
    public function load(string $locale, string $group, ?string $namespace = null): array;
    public function addNamespace(string $namespace, string $path): void;
}
```
