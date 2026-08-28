# Task 013: Inertia Implements ResettableInterface

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Make `Marko\Inertia\Inertia` implement `ResettableInterface`, clearing its shared props. This closes a verified cross-user data leak found by the roadrunner state-leak spike.

## Context
- Modify: `packages/inertia/src/Inertia.php`

**The leak, verified from source.** `Inertia::$shared` is `private array $shared = []` (line 22), written by the public `share()` API (line 36) and merged into every subsequent `render()` call's props. `packages/inertia/module.php` binds `Inertia` as a container **singleton**, and nothing ever clears `$shared`.

In a booted-once process the typical usage pattern — middleware sharing the current authenticated user or flash data once per request — leaves that data visible to **every following request's** Inertia response until the same key is overwritten. If a later request shares a different key, both accumulate indefinitely. This is a cross-request, cross-user data leak, not merely memory growth.

Under PHP-FPM the process ends after each request, so this is a behavioural no-op there — prove that by running the existing `packages/inertia/tests/` suite unchanged.

- Implement `Marko\Core\Contracts\ResettableInterface` on the concrete class. Do **not** add a clearing method to any interface — follow the precedent set by `Session`, `SessionGuard` and `ReadWriteConnection`.
- `reset()` must be **non-destructive**: clear the in-memory per-request shared props, nothing else.
- Check whether `packages/inertia/composer.json` already requires `marko/core`; add it if not.
- Prove the leak with a failing test first: share a value, reset, then assert a subsequent `render()` does not carry it.

## Requirements (Test Descriptions)
- [x] `it implements the resettable contract`
- [x] `it clears shared props when reset`
- [x] `it does not carry shared props into a later render after reset`
- [x] `it leaves shared props intact when not reset`

## Acceptance Criteria
- All requirements have passing tests
- The leak is reproduced by a failing test before being fixed
- All pre-existing `packages/inertia/` tests pass unmodified
- No interface is modified
- Code follows code standards

## Implementation Notes

- `Marko\Inertia\Inertia` now `implements ResettableInterface` (concrete class only, per `Session`/`SessionGuard`/`ReadWriteConnection` precedent — no interface changed).
- `reset()` clears `$shared` to `[]`; nothing else is touched (non-destructive, request-scoped state only).
- The leak was proven first: "it does not carry shared props into a later render after reset" fails without `reset()` clearing `$shared` between two `render()` calls sharing the same `Inertia` instance — this reproduces the exact singleton cross-request leak described in the task.
- `packages/inertia/composer.json` already required `marko/core`; no change needed.
- All pre-existing `packages/inertia/tests/` (54 tests: 50 passed, 2 pre-existing risky, 1 pre-existing skipped — both environment-dependent SSR/curl tests, unrelated to this change) pass unmodified.
- `./vendor/bin/phpcs` clean on both touched files. `phpstan analyse packages/inertia` shows the same 22 pre-existing test-file errors with or without this change (verified via `git stash`) — none in `Inertia.php`; these are a subdirectory-scoping artifact bypassing root `phpstan.neon` exclusions, not caused by this task.
