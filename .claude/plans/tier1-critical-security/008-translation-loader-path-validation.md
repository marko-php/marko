# Task 008: F3 — Validate locale/group/namespace path segments in FileTranslationLoader

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
Stop LFI/RCE in the translation file loader. `FileTranslationLoader::resolveFilePath()` interpolates `$locale`, `$group`, and `$namespace` straight into a path that `load()` then `require`s. Validate each of the three against `^[A-Za-z0-9_-]+$` before assembling the path, in BOTH the namespaced and non-namespaced branches, throwing a loud `TranslationException`. Add a `TranslationException::invalidPathSegment()` factory (the class is currently an empty `MarkoException` subclass).

## Context
- Related files:
  - `packages/translation-file/src/Loader/FileTranslationLoader.php` (`load()` ~21-44; `resolveFilePath()` ~58-81 — both branches)
  - `packages/translation/src/Exceptions/TranslationException.php` (add `invalidPathSegment()` factory)
  - `packages/translation-file/tests/` (Pest)
- Patterns to follow:
  - Three-part `MarkoException` factory (`message`/`context`/`suggestion`, named args), mirroring `InvalidColumnException`.
  - Validate before any filesystem access; the existing "namespace not registered" `TranslationException` shows the constructor usage to mirror.
- Gotchas (verified against source):
  - `resolveFilePath()` has TWO branches: the non-namespaced branch (line ~69) returns immediately with NO guard today; the namespaced branch (line ~80) only checks namespace registration. Validate ALL THREE segments (`$locale`, `$group`, and — when present — `$namespace`) at the TOP of `resolveFilePath()`, before either branch builds a path, so both branches are covered.
  - The empty-string case (`''`) must be rejected — `^[A-Za-z0-9_-]+$` requires at least one char, so an empty locale/group fails. Add a test.
  - `load()` does `require $path` on the resolved path (line ~41), which is the RCE sink — validation must run before `resolveFilePath()` returns, which is before `file_exists()`/`require`.
  - Do not rely on `realpath()` containment alone; the regex denylist of `.`/`/`/`\\`/null-byte via the allowlist pattern is the locked approach. The pattern `^[A-Za-z0-9_-]+$` already excludes `.`, `/`, `\`, and `\0`.

## Requirements (Test Descriptions)
- [x] `it rejects a locale containing a path traversal sequence (../)`
- [x] `it rejects a group containing a path traversal sequence (../)`
- [x] `it rejects a locale containing a null byte`
- [x] `it rejects a group containing a slash or dot path separator`
- [x] `it rejects an invalid namespace path segment in the namespaced branch`
- [x] `it rejects an empty locale or group segment`
- [x] `it still loads a valid locale and group from the non-namespaced branch`
- [x] `it still loads a valid namespaced locale and group from a registered namespace`
- [x] `it throws TranslationException with a helpful suggestion when a segment is invalid`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `TranslationException::invalidPathSegment(string $segment, string $value): self` factory to `packages/translation/src/Exceptions/TranslationException.php` with the three-part MarkoException shape (message/context/suggestion).
- Added `assertValidPathSegment(string $value, string $segment): void` private method to `FileTranslationLoader` that validates against `/^[A-Za-z0-9_-]+$/` — rejects path traversal sequences, slashes, dots, null bytes, and empty strings.
- Validation called at the TOP of `resolveFilePath()` for `$locale` and `$group` (covering both branches), and for `$namespace` before the namespaced branch proceeds — closing LFI/RCE sink (F3).
- All 9 new tests in `packages/translation-file/tests/PathValidationTest.php`; all pass. No pre-existing tests broken.
