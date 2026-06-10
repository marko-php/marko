# Task 008: F3 — Validate locale/group/namespace path segments in FileTranslationLoader

**Status**: pending
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
- [ ] `it rejects a locale containing a path traversal sequence (../)`
- [ ] `it rejects a group containing a path traversal sequence (../)`
- [ ] `it rejects a locale containing a null byte`
- [ ] `it rejects a group containing a slash or dot path separator`
- [ ] `it rejects an invalid namespace path segment in the namespaced branch`
- [ ] `it rejects an empty locale or group segment`
- [ ] `it still loads a valid locale and group from the non-namespaced branch`
- [ ] `it still loads a valid namespaced locale and group from a registered namespace`
- [ ] `it throws TranslationException with a helpful suggestion when a segment is invalid`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
