# Task 003: TranslationConfig, module.php, composer.json for Interface Package

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Create the TranslationConfig class that loads locale settings from the config system, the default config file (`config/translation.php`), the `module.php` with bindings, and the `composer.json` for the `marko/translation` interface package. This wires the translation interface package into the Marko module system.

## Context
- Package: `marko/translation`
- Location: `packages/translation/`
- TranslationConfig reads from `config/translation.php` via ConfigRepositoryInterface
- Config keys: `translation.locale` (default locale), `translation.fallback_locale` (fallback locale)
- module.php binds TranslatorInterface -> Translator
- composer.json follows the pattern from marko/cache, marko/encryption (interface packages)
- Dependencies: marko/core, marko/config
- Reference: `packages/cache/composer.json`, `packages/cache/src/Config/CacheConfig.php`

## Requirements (Test Descriptions)
- [ ] `it creates TranslationConfig with locale and fallback locale from config`
- [ ] `it provides locale and fallbackLocale as readonly properties`
- [ ] `it has valid composer.json with marko module flag and correct dependencies`
- [ ] `it has module.php that binds TranslatorInterface to Translator`
- [ ] `it provides default config file with locale and fallback_locale keys`

## Acceptance Criteria
- TranslationConfig class at `packages/translation/src/Config/TranslationConfig.php`
- TranslationConfig has `public readonly string $locale` and `public readonly string $fallbackLocale`
- TranslationConfig constructor takes ConfigRepositoryInterface and reads `translation.locale` and `translation.fallback_locale`
- `config/translation.php` returns array with `locale` and `fallback_locale` keys using `$_ENV` with defaults
- `composer.json` has name `marko/translation`, type `marko-module`, requires `marko/core` and `marko/config`
- `composer.json` has PSR-4 autoload for `Marko\Translation\` -> `src/`
- `module.php` returns array with bindings mapping TranslatorInterface to Translator
- No hardcoded version in composer.json
- Uses `declare(strict_types=1)`, constructor property promotion

## Implementation Notes
(Left blank)
