# Task 011: Container Resolved-Instances Accessor

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add an accessor to `Container` that exposes the instances it has actually resolved, so a long-running process can find the request-scoped services it needs to reset instead of relying on a hand-maintained list.

## Context
- Modify: `packages/core/src/Container/Container.php`
- The problem this solves: `Container::has()` returns true for any class that merely *exists*, and the internal `$instances` map is private. Without an accessor, a worker's reset list must be hardcoded — and any package that later adds request-scoped singleton state breaks worker mode undetectably, because nothing can discover it.
- **Must NOT force instantiation.** The accessor returns only what has already been resolved. Calling it must never construct a service as a side effect — that would change boot behaviour and could be catastrophic in a worker.
- Purely additive. No existing container behaviour changes.
- Consider the return shape carefully: consumers want to filter for services implementing `ResettableInterface` (task 008), so returning the resolved instances keyed by their binding identifier is more useful than returning identifiers alone.
- This is core, so it IS covered by `phpstan.neon` (which analyzes `packages/core/src`) — it must be clean at level 6.

## Requirements (Test Descriptions)
- [x] `it returns instances that have already been resolved`
- [x] `it does not return bindings that have never been resolved`
- [x] `it does not instantiate anything when called`
- [x] `it returns an empty result for a fresh container`
- [x] `it allows filtering resolved instances by implemented interface`

## Acceptance Criteria
- All requirements have passing tests
- No service is constructed as a side effect of calling the accessor
- PHPStan level 6 clean
- Code follows code standards

## Implementation Notes
- Added `Container::resolvedInstances(?string $interface = null): array<string, object>`. With no argument it returns the private `$instances` map (already-resolved singleton/`instance()` entries) directly — no resolution logic is invoked, so nothing is constructed as a side effect. With an interface argument it `array_filter`s that same map by `instanceof`.
- Only `Container.php` was touched; `ContainerInterface` was left unchanged since Container is the sole implementation and the task scope named only `Container.php`.
- Tests added under a new `resolvedInstances` describe block at the end of `packages/core/tests/Unit/Container/ContainerTest.php`, with fixtures (`CountingServiceInterface`, `CountingService`, `OtherResolvedService`, `InstantiationTrackingService`) placed just above the block, following the file's existing pattern of locating single-purpose fixtures near the describe block that uses them.
- Requirements 2-4 passed immediately once requirement 1's minimal implementation existed (returning the raw `$instances` array already satisfies "no bindings that were never resolved", "no instantiation as a side effect", and "empty for a fresh container") — no extra code was needed for those steps.
- Verified: `composer phpstan` (0 errors), `phpcs`/`php-cs-fixer` clean on touched files, full `composer test` run (6908 passed, 0 failures).
