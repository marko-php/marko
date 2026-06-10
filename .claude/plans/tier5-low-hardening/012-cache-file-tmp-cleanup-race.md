# Task 012: cache-file tmp cleanup, clear glob, mkdir race

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`FileCacheDriver` has three small correctness gaps: (1) on a failed `rename()` in `write()`, the temp file `*.tmp.*` is left behind as an orphan; (2) `clear()` globs only `*.cache`, so leftover temp files are never cleaned; (3) `ensureDirectoryExists()` does a check-then-`mkdir` that races with concurrent processes (the loser's `mkdir` errors even though the directory now exists). Clean the temp file on rename failure, include temp files in `clear()`, and tolerate concurrent directory creation.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/cache-file/src/Driver/FileCacheDriver.php` (`write()` ~265-279; `clear()` ~107-128; `ensureDirectoryExists()` ~294-301)
  - Tests: `/Users/markshust/Sites/marko/packages/cache-file/tests/` (FileCacheDriver tests)
- Patterns to follow:
  - `write()`: temp path is `$filePath . '.tmp.' . uniqid()`. On `rename(...) === false`, `@unlink($tempPath)` before returning `false` so no orphan remains.
  - `clear()`: currently globs `path . '/*.cache'`. Also glob `path . '/*.tmp.*'` and unlink those too; keep the per-file success aggregation and the early-return `true` when the dir is absent / glob returns false handling consistent.
  - `ensureDirectoryExists()`: replace check-then-create with create-then-verify — `@mkdir($path, 0755, true)` and then if the directory still does not exist (`!is_dir($path)`), that is the real failure. A concurrent creator winning the race (mkdir returns false but `is_dir` is true) must NOT error.
  - `clear()` glob detail: temp files are named `{hash}.cache.tmp.{uniqid}`. The existing `*.cache` glob does NOT match them (they don't end in `.cache`), so adding a `*.tmp.*` glob and unlinking the union is correct and non-overlapping. Keep the existing early-return `true` when the dir is absent and the `glob() === false` → `false` handling; apply the same false-handling to the new glob.
  - Tests should simulate a rename failure deterministically. The most reliable cross-platform trick: make the TARGET file path a directory — `mkdir($cachePath . '/' . hash('xxh128', $key) . '.cache')` so `rename($temp, $target)` fails because the target is a non-empty/existing directory. (Confirm the key→filename mapping by reading `getFilePath()`/`hashKey()`: `path . '/' . hash('xxh128', $key) . '.cache'`.) Then assert `set()` returns `false` AND `glob($cachePath . '/*.tmp.*')` is empty (no orphan). Avoid relying on chmod-based unwritable targets (no-op on some CI/root contexts).
  - Pre-seed a `*.tmp.*` file in the cache dir and assert `clear()` removes it AND still removes `.cache` files.
  - For the mkdir race, a single-process test cannot truly race; instead assert the BEHAVIOR: pre-create the cache directory, then exercise a `set()` (which calls `ensureDirectoryExists()`) and assert it does not warn/error and the write succeeds. The create-then-verify shape (`@mkdir(...); if (!is_dir($path)) throw/return-failure`) is what makes a real concurrent winner tolerable; document the chosen failure behavior (the method returns `void` today — keep it `void` and let a still-missing dir surface via the subsequent write failure, OR throw a loud error; pick one and note it. Prefer keeping the signature and letting the write fail loudly since `set()` already returns bool).

## Requirements (Test Descriptions)
- [ ] `it leaves no orphan tmp file when the rename step fails`
- [ ] `it removes leftover tmp files when clear is called`
- [ ] `it still removes cache files when clear is called`
- [ ] `it does not error when the cache directory already exists`
- [ ] `it creates the cache directory when it is missing`
- [ ] `it writes and reads back a value successfully after directory creation`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
