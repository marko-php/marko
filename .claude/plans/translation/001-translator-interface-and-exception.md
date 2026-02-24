# Task 001: TranslatorInterface, TranslationLoaderInterface, and Exceptions

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Create the translation interface package scaffolding and core contracts. This includes TranslatorInterface (the primary translation contract), TranslationLoaderInterface (the contract for loading translation arrays from a source), and the exception hierarchy (TranslationException, MissingTranslationException). These form the foundation that all other tasks depend on.

## Context
- Namespace: `Marko\Translation\`
- Package: `marko/translation`
- Location: `packages/translation/`
- Dependencies: marko/core (for MarkoException base class)
- Pattern: Same as marko/cache, marko/encryption (interface-only packages)
- Reference: `packages/cache/src/Contracts/CacheInterface.php`, `packages/encryption/src/Contracts/EncryptorInterface.php`
- Reference exceptions: `packages/cache/src/Exceptions/CacheException.php`, `packages/cache/src/Exceptions/ItemNotFoundException.php`

## Requirements (Test Descriptions)
- [ ] `it defines TranslatorInterface with get, choice, setLocale, and getLocale methods`
- [ ] `it defines TranslationLoaderInterface with load method accepting locale, group, and optional namespace`
- [ ] `it defines TranslationException extending MarkoException with context and suggestion`
- [ ] `it defines MissingTranslationException extending TranslationException with factory method for missing key`
- [ ] `it includes key, locale, and resolution path in MissingTranslationException context`
- [ ] `it suggests creating the translation file in MissingTranslationException suggestion`

## Acceptance Criteria
- TranslatorInterface has `get(string $key, array $replacements = [], ?string $locale = null): string`
- TranslatorInterface has `choice(string $key, int $count, array $replacements = [], ?string $locale = null): string`
- TranslatorInterface has `setLocale(string $locale): void` and `getLocale(): string`
- TranslationLoaderInterface has `load(string $locale, string $group, ?string $namespace = null): array`
- TranslationException extends MarkoException with three-part constructor (message, context, suggestion)
- MissingTranslationException extends TranslationException with `::forKey(string $key, string $locale)` static factory
- MissingTranslationException suggestion tells the developer which file to create and where
- All classes use `declare(strict_types=1)`, no `final`, constructor property promotion where applicable
- Tests verify interface method signatures via reflection
- Tests verify exception hierarchy and three-part error messages

## Implementation Notes
(Left blank)
