# Plan: Translation / i18n

## Created
2026-02-24

## Status
done

## Objective
Build `marko/translation` (interface) and `marko/translation-file` (driver) -- internationalization services using the interface/driver split pattern. Provides string translation with pluralization support, locale management, and a file-based driver that loads translations from PHP array files with dot-notation keys and module namespace support.

## Scope
### In Scope
- TranslatorInterface with get/choice/setLocale/getLocale methods
- TranslationLoaderInterface for loading translation arrays from a source
- ICU-style pluralization (zero/one/few/many/other)
- Replacement parameters in translation strings
- TranslationConfig for default and fallback locale
- TranslationException and MissingTranslationException (three-part exceptions)
- FileTranslationLoader loading from `lang/{locale}/{group}.php` files
- Dot-notation key resolution (`messages.welcome` -> `messages.php` key `welcome`)
- Namespaced translations (`blog::messages.welcome` -> blog module's lang directory)
- In-memory caching of loaded translation arrays per locale+group
- Package scaffolding for both packages (composer.json, module.php)

### Out of Scope
- Database-backed translations (future: `marko/translation-database`)
- Translation extraction/compilation tools
- JavaScript/frontend translation bundles
- Translation caching to persistent storage (file/redis)
- ICU MessageFormat beyond pluralization
- Right-to-left (RTL) locale detection
- Currency/date/number formatting (separate concern)

## Success Criteria
- [x] TranslatorInterface provides clean get/choice/setLocale/getLocale contract
- [x] TranslationLoaderInterface provides load(locale, group) contract
- [x] Translator implementation resolves keys via loader and applies replacements
- [x] Pluralization handles zero/one/few/many/other variants via pipe-separated syntax
- [x] Fallback locale used when key not found in primary locale
- [x] MissingTranslationException thrown when key not found in any locale
- [x] TranslationConfig loads default locale and fallback locale from config
- [x] FileTranslationLoader loads from `lang/{locale}/{group}.php` files
- [x] Dot-notation keys resolve nested array paths
- [x] Namespaced translations load from module lang directories
- [x] Loaded translations cached in memory to avoid repeated file reads
- [x] Loud error when no translation driver is installed
- [x] All tests passing with >90% coverage on critical paths
- [x] Code follows project standards (strict types, no final, constructor promotion)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | TranslatorInterface, TranslationLoaderInterface, exceptions | - | done |
| 002 | Translator implementation with pluralization | 001 | done |
| 003 | TranslationConfig, module.php, composer.json for interface package | 001 | done |
| 004 | FileTranslationLoader with dot-notation and caching | 001 | done |
| 005 | Namespaced translations (blog::messages.key) | 004 | done |
| 006 | module.php, composer.json for file driver package | 004 | done |

## Architecture Notes

### Package Structure
```
packages/
  translation/                    # Interfaces + shared code
    src/
      Contracts/
        TranslatorInterface.php
        TranslationLoaderInterface.php
      Config/
        TranslationConfig.php
      Exceptions/
        TranslationException.php
        MissingTranslationException.php
      Translator.php
    config/
      translation.php
    tests/
    composer.json
    module.php
  translation-file/               # File-based implementation
    src/
      Loader/
        FileTranslationLoader.php
    tests/
    composer.json
    module.php
```

### Translation File Format
```php
// lang/en/messages.php
return [
    'welcome' => 'Welcome, :name!',
    'goodbye' => 'Goodbye, :name!',
    'items' => 'zero:No items|one:One item|other::count items',
    'nested' => [
        'deep' => 'Nested value',
    ],
];
```

### Interface Design
```php
interface TranslatorInterface
{
    public function get(string $key, array $replacements = [], ?string $locale = null): string;
    public function choice(string $key, int $count, array $replacements = [], ?string $locale = null): string;
    public function setLocale(string $locale): void;
    public function getLocale(): string;
}

interface TranslationLoaderInterface
{
    /** @return array<string, mixed> */
    public function load(string $locale, string $group, ?string $namespace = null): array;
}
```

### Pluralization Format (pipe-separated with labels)
```
'items' => 'zero:No items|one:One item|other::count items'
```
The `choice()` method selects the appropriate variant based on count:
- `zero` when count === 0
- `one` when count === 1
- `few` when count 2-4 (language-dependent, future)
- `many` when count 5+ (language-dependent, future)
- `other` as default fallback

### Key Resolution
- Simple: `messages.welcome` -> loads `messages` group, accesses `welcome` key
- Nested: `messages.nested.deep` -> loads `messages` group, accesses `nested.deep` via dot notation
- Namespaced: `blog::messages.welcome` -> loads `messages` group from `blog` module's lang dir

### Namespace Registration
Modules register their lang directory paths so FileTranslationLoader knows where to look:
```php
$loader->addNamespace('blog', '/path/to/packages/blog/lang');
```

### Config
```php
// config/translation.php
return [
    'locale' => $_ENV['APP_LOCALE'] ?? 'en',
    'fallback_locale' => $_ENV['APP_FALLBACK_LOCALE'] ?? 'en',
];
```

### Module Bindings

**translation/module.php**
```php
return [
    'bindings' => [
        TranslatorInterface::class => Translator::class,
    ],
];
```

**translation-file/module.php**
```php
return [
    'bindings' => [
        TranslationLoaderInterface::class => FileTranslationLoader::class,
    ],
];
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Missing translation file | MissingTranslationException with clear message about which file was expected and where to create it |
| Invalid pluralization format | TranslationException with the malformed string and expected format in suggestion |
| Namespace not registered | MissingTranslationException with suggestion to register the namespace in the module |
| Deeply nested keys not found | Return the key as-is or throw, with context showing the full resolution path attempted |
| Memory usage from caching | Cache is per-request only (no persistent cache); translations loaded lazily by group |
| Locale not supported | Fallback locale used first; MissingTranslationException only if both fail |
