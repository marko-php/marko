# Task 010: Implement translation file scanner in codeindexer

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Implement a `TranslationScanner` service that walks `resources/translations/{locale}/{group}.php` across modules and produces an index of translation keys in dot-notation (`group.nested.key`) scoped by locale and (optional) namespace.

## Context
- Namespace: `Marko\CodeIndexer\Translations\TranslationScanner`
- Output type: `TranslationEntry { string $key, string $group, string $locale, ?string $namespace, string $file, int $line }`
- Namespace form: `namespace::group.key` parsed to (namespace, group, key)
- Handles locale discovery dynamically (no hardcoded locale list)
- Reads translation array via AST parsing only (no file inclusion) — same rationale as ConfigScanner

## Requirements (Test Descriptions)
- [ ] `it discovers translation files across locales in every module`
- [ ] `it flattens nested translation arrays to dot notation`
- [ ] `it captures namespace when translations live under a namespaced module`
- [ ] `it records source file and line for each top-level key`
- [ ] `it returns empty for modules without resources/translations`
- [ ] `it groups entries by locale for fallback-aware completion`

## Acceptance Criteria
- Fixtures cover multiple locales and namespaced modules
- Entries are consistent with `Translator::parseKey()` in `marko/translation`

## Implementation Notes
(Filled in by programmer during implementation)
