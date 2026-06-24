# Task 001: ConfirmationPrompterInterface + StdinPrompter + FakePrompter

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Introduce a small, injectable interactive-confirmation seam inside the devai
package, because `Marko\Core\Command\Input`/`Output` have no interactive input.
A real `StdinPrompter` reads a Y/n answer from STDIN and reports whether the
session is interactive; a `FakePrompter` lets tests script answers without
touching real STDIN.

## Context
- New files under `packages/devai/src/Process/` (peer of `CommandRunner`):
  `ConfirmationPrompterInterface.php`, `StdinPrompter.php`.
- Interface shape (typed):
  - `isInteractive(): bool`
  - `confirm(string $question, bool $default): bool`
- **`StdinPrompter` must be unit-testable without reading real STDIN.** Hardcoding
  `STDIN`/`stream_isatty(STDIN)`/`fgets(STDIN)` makes the parsing logic impossible
  to test on the real class. Therefore `StdinPrompter` takes an **injectable input
  stream resource** plus the no-interaction flag, both constructor-promoted/readonly:
  `__construct(private $stream = STDIN, private bool $noInteraction = false)`
  (note: a resource cannot be type-declared, so `$stream` is untyped — document
  this with a `@param resource $stream` docblock). Tests pass a `php://memory`/
  `php://temp` stream pre-filled with the scripted answer, so `confirm()`'s real
  parsing runs under test. Only `isInteractive()`'s `stream_isatty()` call against a
  real TTY stays untested — keep that the single untested line, decomposed so
  `confirm()` does not depend on it.
- `isInteractive()` = `!$this->noInteraction && stream_isatty($this->stream)`.
- `confirm()` reads one line via `fgets($this->stream)`, then parses a trimmed,
  lowercased line: `y`/`yes` → true, `n`/`no` → false, empty → `$default`; any other
  input is treated as `$default` (no re-read).
- Test double: `FakePrompter` — it is a **test-only double**, NOT a production class.
  Define it inside the test file(s) as an anonymous class or a small named class in
  the `Marko\DevAi\Tests\` namespace under `packages/devai/tests/` (the registered
  `autoload-dev` psr-4 is `Marko\DevAi\Tests\ => tests/`). Do **not** add a binding
  for it and do **not** place it under `src/`. It implements
  `ConfirmationPrompterInterface` with a scripted boolean answer and a configurable
  `isInteractive()` flag. (The codebase uses inline anonymous-class doubles —
  see `makeRecordingRunner()` in `InstallationOrchestratorTest` — follow that pattern.)
- Bind in `packages/devai/module.php`: `ConfirmationPrompterInterface::class => StdinPrompter::class`
  (alongside the existing `CommandRunnerInterface` binding). `StdinPrompter` autowires
  with its default `STDIN`/`false` args (container passes no constructor values), so the
  container binding resolves with no extra wiring.
- Standards: `declare(strict_types=1)`, constructor property promotion, type decls,
  no `final`. Interface methods fully typed.

## Requirements (Test Descriptions)
- [x] `it returns true when the user answers yes` (StdinPrompter, in-memory stream)
- [x] `it returns false when the user answers no` (StdinPrompter, in-memory stream)
- [x] `it returns the default when the answer is empty` (StdinPrompter, in-memory stream)
- [x] `it parses answers case-insensitively and ignores surrounding whitespace` (StdinPrompter)
- [x] `it reports not interactive when constructed in no-interaction mode` (StdinPrompter)
- [x] `the fake prompter returns its scripted answer and configured interactivity`

## Acceptance Criteria
- All requirements have passing tests (using `FakePrompter`, never real STDIN)
- `ConfirmationPrompterInterface` bound to `StdinPrompter` in `module.php`
- Code follows code standards; no decrease in coverage

## Implementation Notes
- Created `ConfirmationPrompterInterface` with `isInteractive(): bool` and `confirm(string $question, bool $default): bool`
- Created `StdinPrompter` with injectable `$stream` resource (untyped, `@param resource` docblock) and `bool $noInteraction = false`. Uses `stream_isatty()` for `isInteractive()`, `fgets()` for `confirm()` with trim+lowercase parsing
- `FakePrompter` implemented as inline anonymous class in `makeFakePrompter()` helper inside the test file, following the `makeRecordingRunner()` pattern
- Bound `ConfirmationPrompterInterface::class => StdinPrompter::class` in `module.php` alongside `CommandRunnerInterface`
- All 6 requirements have passing tests; 228 total package tests pass
