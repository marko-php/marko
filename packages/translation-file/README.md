# marko/translation-file

File-based translation loader--reads translation arrays from PHP files organized by locale and group.

## Installation

```bash
composer require marko/translation-file
```

This package provides the file-based implementation for `marko/translation`.

## Quick Example

```php
// lang/en/messages.php
return [
    'welcome' => 'Welcome to our site!',
    'hello' => 'Hello, :name!',
];
```

## Documentation

Full usage, API reference, and examples: [marko/translation-file](https://marko.build/docs/packages/translation-file/)
