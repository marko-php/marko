# Task 004: FileTranslationLoader with Dot-Notation and Caching

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Implement FileTranslationLoader, which loads translation arrays from PHP files on disk. Translation files live at `lang/{locale}/{group}.php` and return associative arrays. The loader caches loaded arrays in memory to avoid repeated file reads within the same request. It supports nested arrays accessed via dot notation by the Translator.

## Context
- Class: `Marko\Translation\File\Loader\FileTranslationLoader`
- Package: `marko/translation-file`
- Location: `packages/translation-file/src/Loader/FileTranslationLoader.php`
- File structure: `{basePath}/lang/{locale}/{group}.php` (e.g., `lang/en/messages.php`)
- Each PHP file returns `array<string, mixed>` (can have nested arrays)
- The loader returns the full array for a group; dot-notation traversal is the Translator's responsibility
- In-memory cache keyed by `{namespace}::{locale}.{group}` (or `{locale}.{group}` for default namespace)
- When a file does not exist, return an empty array (the Translator decides whether to throw)
- Reference: `packages/cache-file/src/Driver/FileCacheDriver.php` for file-based driver pattern

## Requirements (Test Descriptions)
- [ ] `it implements TranslationLoaderInterface`
- [ ] `it loads translation array from PHP file at lang/{locale}/{group}.php`
- [ ] `it returns empty array when translation file does not exist`
- [ ] `it caches loaded translations in memory for same locale and group`
- [ ] `it loads different groups independently`
- [ ] `it loads different locales independently`

## Acceptance Criteria
- FileTranslationLoader implements TranslationLoaderInterface
- Constructor accepts a base path string (the root directory containing `lang/`)
- `load('en', 'messages')` reads from `{basePath}/lang/en/messages.php`
- `load('fr', 'validation')` reads from `{basePath}/lang/fr/validation.php`
- Returns the full array from the PHP file (no key traversal at this level)
- Returns empty array `[]` when the file does not exist (no exception)
- Second call to `load('en', 'messages')` returns cached result without re-reading file
- Loading `('en', 'messages')` and `('en', 'validation')` are separate cache entries
- Loading `('en', 'messages')` and `('fr', 'messages')` are separate cache entries
- Uses `declare(strict_types=1)`, no `final`, constructor property promotion
- Tests use temporary directories with real PHP files (no mocking the filesystem)

## Implementation Notes
(Left blank)
