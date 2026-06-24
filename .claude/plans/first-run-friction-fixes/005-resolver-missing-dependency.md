# Task 005: Distinguish missing dependency from circular dependency

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`DependencyResolver::resolve()` throws `CircularDependencyException` whenever Kahn's topological sort can't place every enabled module — and when the incompleteness is NOT caused by a real cycle, `findCycle()` correctly returns `[]`, producing the unusable message "Circular dependency detected: " with an empty chain (the CLI `module:list`/`route:list` boot bug). Split the two failure modes into distinct loud exceptions: when there is no real cycle, throw a new `MissingDependencyException` naming the unsorted module(s) and their unmet ordering/dependency constraints; only throw `CircularDependencyException` when a real, populated cycle exists.

**Important — read before implementing.** Verify the actual mechanics against the source; the original framing was imprecise:
- A **disabled** required dependency is ALREADY caught loudly BEFORE Kahn runs, at `DependencyResolver.php:42-49`, via `ModuleException::missingDependency()`. It never reaches the empty-chain path. This task should REDIRECT that existing disabled-dependency check to the new `MissingDependencyException` (so missing/disabled report through one consistent exception type per the locked decision), and fix its message which currently says "not installed" even when the dependency is present-but-disabled.
- An **absent** `require` dependency (not in the module list at all) is intentionally SKIPPED in the graph (`isset($modulesByName[$dependency])`, lines 63-69) because it may be a plain Composer package (`psr/container`) — so an absent `require` does NOT by itself destabilize the sort and does NOT reach the empty-chain path.
- The empty-chain-after-Kahn path is therefore reached by **soft-ordering (`after`/`before`) deadlocks** — e.g. a module that declares both `after: [X]` and `before: [X]`, or a `before`/`after` graph with no node of in-degree zero — where Kahn can't start/complete yet `findCycle()` may or may not find a populated cycle. The fix must produce a useful, loud message for this case naming the unsorted module(s) and the ordering constraints involved, never an empty "Circular dependency detected: ".

## Context
- Related files:
  - `packages/core/src/Module/DependencyResolver.php` (existing disabled-dep check at lines ~42-49; throw site at ~112-115; `findCycle()` / `detectCycleDfs()` below it)
  - `packages/core/src/Exceptions/CircularDependencyException.php` (factory `detected()`)
  - `packages/core/src/Exceptions/ModuleException.php` (existing `missingDependency()` factory — message wording is inaccurate for the disabled case)
  - New: `packages/core/src/Exceptions/MissingDependencyException.php` (follow `MarkoException` shape: message + context + suggestion; mirror `CircularDependencyException` style)
- Patterns to follow: existing `Exceptions/` classes; loud-error format (what/where/how-to-fix).
- Logic:
  1. Replace the disabled-dependency throw at lines 42-49 with `MissingDependencyException` naming the requiring module and the disabled dependency, with a message distinguishing "present but disabled".
  2. After Kahn's, when `count($sorted) !== count($enabledModules)`, compute the unsorted set. Run `findCycle()`. If it returns a non-empty chain → `CircularDependencyException::detected($cycle)` (real cycle, populated). If empty → `MissingDependencyException` listing each unsorted module and the specific unmet ordering constraints (`after`/`before` targets) and/or required modules that left it unplaceable.
- The new exception must NEVER be thrown with an empty offender list, and `CircularDependencyException` must NEVER be thrown with an empty chain.

## Requirements (Test Descriptions)
- [x] `it throws a missing dependency error naming a module that requires a disabled dependency`
- [x] `it states the dependency is present but disabled rather than not installed`
- [x] `it throws a missing dependency error (not an empty-chain circular error) for a before-after soft-ordering deadlock`
- [x] `it names the unsorted modules and their unmet ordering constraints in the missing-dependency message`
- [x] `it throws a circular dependency error with a populated chain for a real two-module require cycle`
- [x] `it includes every node of the cycle in the chain for a real three-module require cycle`
- [x] `it resolves successfully and throws nothing when every dependency is satisfied`
- [x] `it still resolves successfully when an enabled module requires a non-marko composer package (absent from the module list)`

## Acceptance Criteria
- No code path throws `CircularDependencyException` with an empty chain; no code path throws `MissingDependencyException` with an empty offender list.
- Disabled dependency and real cycle and soft-ordering deadlock are each reported by a loud, distinct, populated exception (`MissingDependencyException` for the first and third, `CircularDependencyException` for the second).
- The disabled-dependency message no longer claims "not installed" for a present-but-disabled module.
- CLI `module:list` / `route:list` boot on a satisfiable graph; on an unsatisfiable one they name the offending module + constraint.
- All requirements have passing tests; lint clean; no coverage decrease.

## Implementation Notes
- Created `MissingDependencyException` with two static factories: `dependencyDisabled()` for present-but-disabled deps, `orderingDeadlock()` for soft-ordering deadlocks.
- In `DependencyResolver`, the disabled-dependency check at lines 42-49 now throws `MissingDependencyException::dependencyDisabled()` instead of `ModuleException::missingDependency()`.
- Added a separate `$requireDependents` graph (hard `require` edges only) alongside the combined `$dependents` graph. `findCycle()` now operates on `$requireDependents` so soft-ordering cycles (after/before) are never misidentified as `CircularDependencyException`.
- After Kahn's sort, if unsorted modules remain and `findCycle()` returns `[]` (no require cycle), the code computes per-module unmet constraints (after:/before:/require: prefixed) and throws `MissingDependencyException::orderingDeadlock()`.
- Old test `it throws ModuleException when enabled module requires disabled module` updated to use `MissingDependencyException`.
- 8 new tests added; total DependencyResolverTest count went from 11 to 19; full core suite: 556 passed.
