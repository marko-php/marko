# Task 002: F2 — errors-advanced standalone boot + real register()/handleError()

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
`marko/errors-advanced` is unusable: `AdvancedErrorHandler::register()` is an empty
no-op and `handleError()` is a bare `return true` that silently swallows every
warning, notice, and deprecation. Implement `register()`/`unregister()` to actually
install and restore PHP's error/exception/shutdown handlers, and make `handleError()`
surface non-fatal errors loudly (matching `SimpleErrorHandler`'s behaviour) instead
of swallowing them. The package keeps binding `ErrorHandlerInterface` itself (the
locked design: each driver binds independently; installing two drivers is a
deliberate loud `BindingConflictException`).

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/errors-advanced/src/AdvancedErrorHandler.php`
    (`register()` is `{}`; `unregister()` is `{}`; `handleError(...)` is `return true`)
  - `/Users/markshust/Sites/marko/packages/errors-advanced/module.php`
    (binds `ErrorHandlerInterface => AdvancedErrorHandler`; boot calls `register()`)
  - `/Users/markshust/Sites/marko/packages/errors-simple/src/SimpleErrorHandler.php`
    (REFERENCE implementation: full `register()`/`unregister()`/`handleError()`/
    `handleShutdown()`/`handleNonFatal()` with `error_reporting()` respect, stderr
    surfacing of deprecations/notices, fatal handling via shutdown)
  - `/Users/markshust/Sites/marko/packages/errors/src/Contracts/ErrorHandlerInterface.php`
  - `/Users/markshust/Sites/marko/packages/errors-advanced/tests/Unit/AdvancedErrorHandlerTest.php`
- Patterns to follow:
  - Mirror `SimpleErrorHandler`'s `register/unregister/handleError/handleShutdown`
    structure. `AdvancedErrorHandler` today has NONE of the state SimpleErrorHandler
    relies on — you must ADD: `bool $registered`, `mixed $previousExceptionHandler`,
    `mixed $previousErrorHandler`, `bool $handledFatalError`, a `handleShutdown()` method
    (registered via `register_shutdown_function`, fatal-type masked, idempotent), and a
    `handleNonFatal()` path for deprecations/notices. Reuse errors-advanced's own pretty
    formatters for the report rendering.
  - **Do NOT clear output buffers in the advanced handler's non-fatal path.**
    `AdvancedErrorHandler::handle()` deliberately does not call `clearOutputBuffers()`
    (unlike `SimpleErrorHandler`) because pretty rendering needs the buffer. Keep that
    behaviour; the non-fatal deprecation/notice path writes to stderr (CLI) like
    `SimpleErrorHandler::handleNonFatal()` and returns without touching buffers.
  - **`handleError()` must respect `error_reporting()`**: return `false` for a level
    masked off by the current `error_reporting` mask (matching SimpleErrorHandler), so
    suppressed/`@`-silenced errors are not surfaced.
  - **Tests must restore global handler state.** `register()` installs real
    `set_error_handler`/`set_exception_handler`/`register_shutdown_function`. Every test
    that calls `register()` (directly or via the module `boot`) MUST call `unregister()`
    in an `afterEach`/`finally` so handler state does not leak into sibling tests and
    corrupt the parallel suite. Trigger non-fatals with `trigger_error(..., E_USER_*)`
    and capture stderr; do not rely on real fatal errors in unit tests.
  - Loud-errors standard (`.claude/code-standards.md`): never silently swallow.
  - errors-advanced already `require`s `marko/errors-simple: self.version`, so reusing
    errors-simple `src/` classes (Environment, CodeSnippetExtractor, formatters) at the
    code level is fine; the binding must remain on `AdvancedErrorHandler`.

## Requirements (Test Descriptions)
- [x] `it installs an error handler and an exception handler when register() is called`
- [x] `it restores the previously installed handlers when unregister() is called`
- [x] `it does not swallow warnings: handleError() surfaces a non-fatal warning loudly
      rather than returning true with no side effect`
- [x] `it surfaces deprecations and notices without halting execution or clearing output buffers`
- [x] `it respects error_reporting(): handleError() returns false for a level masked off
      by the current error_reporting setting`
- [x] `it is idempotent: calling register() twice installs handlers only once`
- [x] `it registers a shutdown handler that surfaces a fatal error captured at shutdown
      (handleShutdown is idempotent and only acts on fatal error types)`
- [x] `it boots successfully with errors-advanced as the sole error driver (module bindings
      load and the booted handler reports E_USER_WARNING loudly)`
- [x] `each test restores prior handler state via unregister() so global handlers do not
      leak across the suite`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes

- Added `$registered`, `$previousExceptionHandler`, `$previousErrorHandler`, `$handledFatalError` state fields to `AdvancedErrorHandler`
- Implemented `register()` (idempotent, stores previous handlers), `unregister()` (restores previous handlers), `handleShutdown()` (fatal-type masking, idempotent), `handleNonFatal()` (writes to stderr in CLI, no buffer clearing), and real `handleError()` (respects `error_reporting()`, converts to `ErrorException`, routes non-fatals to `handleNonFatal` and fatals to `handleException`)
- `TestableAdvancedHandler` subclass in the test file overrides `handleNonFatal()` to capture reports and exposes `isRegistered()` for state inspection
- Tests use `beforeEach`/`afterEach` pattern (mirroring `errors-simple`'s `HandlerRegistrationTest`) to save/restore global handler state and avoid risky test detection
- Boot test uses an inline anonymous stub `ContainerInterface` to exercise the `module.php` boot closure without requiring a real container
