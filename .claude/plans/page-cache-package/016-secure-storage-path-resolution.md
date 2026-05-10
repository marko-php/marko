# Task 016: Secure Storage Path Resolution in FilePageCacheDriver

**Status**: completed
**Depends on**: 011, 012
**Retry count**: 0

## Description

`FilePageCacheDriver` stores cache files at the path returned by `PageCacheConfig::path()`, which defaults to the relative string `'storage/page-cache'`. Without explicit resolution, this path is joined against PHP's CWD at the time of the call. When a web server sets CWD to `public/`, the files land in `public/storage/page-cache` — publicly accessible over HTTP. This task fixes the driver to resolve relative paths against the project root (`ProjectPaths->base`), matching the established `DebugbarStorage` pattern.

## Context

**Why this bug exists:** Three path-resolution patterns exist in the codebase:
1. `FileCacheDriver` — raw relative path passed as-is (same latent bug).
2. `FileSessionHandler` — resolves via `getcwd()` (still CWD-dependent, just later).
3. `DebugbarStorage` — injects `ProjectPaths`, checks `isAbsolutePath()`, resolves relative paths against `$paths->base`. This is the correct approach.

**Scope of this fix:** Only `FilePageCacheDriver` (in `marko/page-cache-file`). Fixing `FileCacheDriver` or `FileSessionHandler` is out of scope for this task — they are separate packages and the user requested a focused follow-up here. The config default in `config/page-cache.php` (`'storage/page-cache'`) is intentionally kept as-is; it is a relative path that the driver correctly resolves.

**No `storage` property on `ProjectPaths`:** `ProjectPaths` (in `packages/core/src/Path/ProjectPaths.php`) has `base`, `vendor`, `modules`, `app`, `config`, `database` — but no `storage`. The driver should resolve against `$paths->base`, not a missing `$paths->storage`. Adding a `storage` property to `ProjectPaths` is out of scope for this task.

- Related files:
  - `packages/page-cache-file/src/Driver/FilePageCacheDriver.php` — inject `ProjectPaths`, add `resolvedPath()` private helper
  - `packages/page-cache-file/src/module.php` — update DI binding if constructor signature changes
  - `packages/page-cache-file/tests/Unit/Driver/FilePageCacheDriverTest.php` — existing tests already use real `tmpDir` absolute paths, so they pass without change; add a new test for relative-path resolution
  - `packages/page-cache-file/tests/Unit/Driver/FilePageCacheDriverTagsTest.php` — same
  - `packages/core/src/Path/ProjectPaths.php` — read-only reference for the class shape (do not modify)
  - `packages/debugbar/src/Storage/DebugbarStorage.php` — reference implementation to follow

- Pattern to follow: `DebugbarStorage` constructor injection + `isAbsolutePath()` private method.

## Requirements (Test Descriptions)

- [x] `it resolves a relative path against the project base directory`
- [x] `it uses an absolute path as-is when configured with an absolute path`
- [x] `it stores page cache files outside the public directory when using the default relative path`

## Acceptance Criteria

- All requirements have passing tests
- `FilePageCacheDriver` injects `ProjectPaths` and resolves relative `path()` values against `$paths->base`
- Absolute paths (starting with `/`) are used as-is — no double-resolution
- No change to `PageCacheConfig::path()` return value (resolution is driver concern, not config concern)
- `module.php` DI binding updated if needed so the container can wire `ProjectPaths` into the driver
- All existing `FilePageCacheDriver` tests still pass
- Code follows project standards (strict types, constructor property promotion, no final, etc.)

## Implementation Notes

(Left blank - filled in by programmer during implementation)
