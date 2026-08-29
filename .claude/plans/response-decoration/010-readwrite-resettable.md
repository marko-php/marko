# Task 010: ReadWriteConnection Implements ResettableInterface

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Make `ReadWriteConnection` implement `ResettableInterface`, delegating to its existing `resetStickyState()`. This converts the one service that already anticipated worker mode from a bespoke concrete method into the shared contract, so a worker can discover it uniformly.

## Context
- Modify: `packages/database-readwrite/src/` — the `ReadWriteConnection` class
- `resetStickyState()` already exists and is already documented for exactly this scenario. From that package's own plan: *"Long-running processes (queue workers, Swoole, RoadRunner): `resetStickyState()` is a public method... v1 ships the method but does not auto-wire it."* This task is that wiring.
- Keep `resetStickyState()` as a public method — it is documented in `packages/docs-markdown/docs/packages/database-readwrite.md` and removing it would be a BC break for anyone already calling it. The interface method should delegate to it, not replace it.
- Behaviour must be unchanged: all pre-existing `database-readwrite` tests pass untouched.
- Update that package's docs page to mention it now satisfies the contract.

## Requirements (Test Descriptions)
- [x] `it implements the resettable contract`
- [x] `it clears sticky write state when reset`
- [x] `it routes reads to a replica again after reset`
- [x] `it keeps the existing reset sticky state method available`

## Acceptance Criteria
- All requirements have passing tests
- All pre-existing `database-readwrite` tests pass unmodified
- `resetStickyState()` remains public
- Code follows code standards

## Implementation Notes
- `ReadWriteConnection` now implements `Marko\Core\Contracts\ResettableInterface` in addition to `ConnectionInterface`/`TransactionInterface`.
- New public `reset(): void` method delegates to the existing `resetStickyState()`, which is unchanged and remains public (BC preserved).
- `marko/database-readwrite`'s `composer.json` already required `marko/core`, so no dependency change was needed.
- The requirement `it keeps the existing reset sticky state method available` passed immediately once written (RED phase produced a pass, not a fail) — `resetStickyState()` already existed prior to this task from task 008's prerequisite work, so there was nothing new to implement for that specific test. Noted here per TDD process rather than silently skipped.
- Docs updated at `packages/docs-markdown/docs/packages/database-readwrite.md`: Long-Running Processes section now explains `ResettableInterface`/`reset()` wiring, the API reference "Implements" line now lists `ResettableInterface`, and a `reset(): void` row was added to the method table.
- Full package suite (`packages/database-readwrite/tests/`) — 83 tests passed, all pre-existing tests unmodified. `composer phpstan` and `phpcs`/`php-cs-fixer` clean on touched files.
