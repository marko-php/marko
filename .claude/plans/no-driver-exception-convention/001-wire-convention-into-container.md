# Task 001: Wire NoDriverException Convention into Container

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Modify the container to detect and throw package-specific `NoDriverException` instead of the generic `BindingException` when resolving unbound Marko interfaces. Also remove the filesystem-scanning discovery methods from `BindingException`.

## Context
- Related files:
  - `packages/core/src/Container/Container.php` (line 138-139 — the `noImplementation` throw)
  - `packages/core/src/Exceptions/BindingException.php` (remove `discoverDriverPackages`, `scanForDriverPackages`, update `noImplementation`)
  - `packages/core/tests/Unit/Exceptions/ExceptionsTest.php`
  - `packages/core/tests/Unit/Container/ContainerTest.php`
- The convention: when resolving `Marko\{Package}\SomeInterface` fails, check if `Marko\{Package}\Exceptions\NoDriverException` exists with a `noDriverInstalled()` method

## Requirements (Test Descriptions)
- [ ] `it throws NoDriverException when interface package has one and no binding exists`
- [ ] `it throws generic BindingException when no NoDriverException class exists for the package`
- [ ] `it falls back to BindingException for non-Marko interfaces`
- [ ] `it does not check for NoDriverException on non-interface classes`
- [ ] `BindingException no longer has discoverDriverPackages or scanForDriverPackages methods`
- [ ] `BindingException noImplementation still works as generic fallback`

## Acceptance Criteria
- All requirements have passing tests
- Container correctly delegates to package-specific NoDriverException
- Filesystem scanning completely removed from BindingException
- No decrease in test coverage

## Implementation Notes
The check should happen at line 138-139 of Container.php. Before throwing `BindingException::noImplementation($id)`, extract the namespace from `$id`:
1. Check if `$id` starts with `Marko\`
2. Extract the second segment (e.g., `View` from `Marko\View\Contracts\ViewInterface`)
3. Check `class_exists("Marko\\{$segment}\\Exceptions\\NoDriverException")`
4. Check `method_exists()` for `noDriverInstalled`
5. If both pass, throw `$class::noDriverInstalled()` instead of `BindingException::noImplementation($id)`

This is on the error path only so performance is not a concern.

**Simplified `noImplementation()`**: After removing `discoverDriverPackages()` and `scanForDriverPackages()`, simplify `noImplementation()` to just suggest registering a binding in module.php. No driver suggestions -- the container handles that via `NoDriverException` now. The suggestion MUST still contain the word "binding" (existing tests at `ExceptionsTest.php` line 142 assert `->toContain('bind')`).

**Test fixture**: The test fixture `NoDriverException` must be a real class in a `Marko\{Something}\Exceptions` namespace (e.g., `Marko\TestFixture\Exceptions\NoDriverException`) to be discoverable by the convention. Create a separate fixture file or use a namespaced inline definition. The existing `ContainerTest.php` fixtures are in the global namespace and won't work for this test. Also create a fixture interface in the same `Marko\TestFixture` namespace to trigger the container check.
