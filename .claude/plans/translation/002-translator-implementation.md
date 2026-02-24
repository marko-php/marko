# Task 002: Translator Implementation with Pluralization

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Implement the Translator class that implements TranslatorInterface. It uses a TranslationLoaderInterface to load translation arrays, resolves dot-notation keys, applies string replacements (`:name` style), supports locale fallback, and provides ICU-style pluralization via the `choice()` method using pipe-separated format (`zero:No items|one:One item|other::count items`).

## Context
- Class: `Marko\Translation\Translator`
- Location: `packages/translation/src/Translator.php`
- Depends on: TranslatorInterface, TranslationLoaderInterface, TranslationConfig (from task 003, but can use constructor injection with locale/fallback strings for now)
- Key resolution: first segment before first dot is the group, remainder is the array key path
- Replacement format: `:name` in translation string replaced with value from replacements array
- Pluralization: pipe-separated with labels (`zero:`, `one:`, `few:`, `many:`, `other:`)
- Fallback: if key not found in requested locale, try fallback locale before throwing MissingTranslationException
- Reference: Similar to Laravel's Translator concept but with explicit contracts and loud errors

## Requirements (Test Descriptions)
- [ ] `it implements TranslatorInterface`
- [ ] `it resolves simple translation key via loader`
- [ ] `it resolves nested dot-notation keys from loaded arrays`
- [ ] `it replaces :placeholder tokens with provided replacements`
- [ ] `it falls back to fallback locale when key missing in primary locale`
- [ ] `it throws MissingTranslationException when key missing in all locales`
- [ ] `it selects correct plural form for zero, one, and other counts`

## Acceptance Criteria
- Translator constructor accepts TranslationLoaderInterface, default locale string, and fallback locale string
- `get('messages.welcome', ['name' => 'Mark'])` loads group `messages`, resolves key `welcome`, replaces `:name`
- `get('messages.nested.deep')` loads group `messages`, traverses `nested` -> `deep` in the array
- `choice('messages.items', 0)` returns the `zero:` variant
- `choice('messages.items', 1)` returns the `one:` variant
- `choice('messages.items', 5, ['count' => 5])` returns the `other:` variant with `:count` replaced
- When a key like `messages.welcome` is not found in locale `fr`, falls back to `en` before throwing
- `setLocale()` and `getLocale()` change and return the current locale
- Translation arrays loaded from loader are cached in memory (same group+locale not loaded twice)
- No `final` class, uses `declare(strict_types=1)`

## Implementation Notes
(Left blank)
