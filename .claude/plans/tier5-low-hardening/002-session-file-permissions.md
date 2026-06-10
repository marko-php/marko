# Task 002: Session-file restrictive permissions + checked writes

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`FileSessionHandler` creates its session directory with `0755` and writes session files via `fopen('c')` without any `chmod`, so files inherit the umask and may be world/group-readable. It also calls `fwrite()`/`ftruncate()` without checking return values, so a partial or failed write silently truncates the session. Restrict session files to `0600` (and the directory to `0700`) and surface failed/partial writes as a loud error.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/session-file/src/Handler/FileSessionHandler.php` (`open()` mkdir ~31; `write()` fopen/ftruncate/fwrite ~66-85)
  - New: `/Users/markshust/Sites/marko/packages/session-file/src/Exceptions/SessionWriteException.php` (extends `Marko\Core\Exceptions\MarkoException`)
  - Tests: `/Users/markshust/Sites/marko/packages/session-file/tests/Unit/FileSessionHandlerTest.php`
- Patterns to follow:
  - Existing test helpers `getSessionTestPath()`, `createSessionConfig()`, `cleanupSessionTestPath()` at the top of `FileSessionHandlerTest.php`; reuse them.
  - Loud-error exception factory with `message`/`context`/`suggestion` (mirror `LogWriteException::forPath()`).
  - Assert permissions with `fileperms($file) & 0777`.
  - Keep `SessionHandlerInterface` signatures unchanged (`write()` still returns `bool`); throw the new exception on partial/failed write rather than returning `false` silently. Because `SessionWriteException` extends `MarkoException` (unchecked), the interface contract is not broken; do NOT add it to the interface signature, but DO add a `@throws SessionWriteException` PHPDoc tag on the `write()` implementation.
  - `fopen($path, 'c')` does NOT truncate and opens BOTH new and existing files, so it does not tell you whether the file was just created. `chmod($path, 0600)` UNCONDITIONALLY after a successful `fopen('c')` (chmod is idempotent and also re-tightens an existing file that was created loosely — this is what the "rewrite leaves 0600" requirement needs). Apply chmod while you hold the lock, before returning.
  - Partial-write detection: `fwrite($handle, $data)` returns the number of bytes written or `false`. Treat `$written === false || $written !== strlen($data)` as a partial/failed write and throw `SessionWriteException`. `ftruncate($handle, 0)` returns `bool` — treat `false` as failure and throw. Close the handle and release the lock (e.g. in a `finally` or before throwing) so a failed write does not leak the file handle / lock.
  - `open()`: replace the `mkdir($this->path, 0755, true)` with `0700` for the directory. Guard that a concurrent/failed mkdir which still leaves the dir present is tolerated (mirror Task 012's create-then-verify if needed), but keeping the existing `if (!is_dir(...))` guard plus `0700` is sufficient for this task's scope.

## Requirements (Test Descriptions)
- [ ] `it creates a session file with 0600 permissions after write`
- [ ] `it creates the session directory with 0700 permissions on open`
- [ ] `it throws SessionWriteException when fwrite does not write all bytes`
- [ ] `it throws SessionWriteException when ftruncate fails`
- [ ] `it still reads back exactly what was written for a normal write`
- [ ] `it leaves an existing session file at 0600 after a subsequent rewrite`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
