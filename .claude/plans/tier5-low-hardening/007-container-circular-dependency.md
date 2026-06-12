# Task 007: Core container circular-dependency detection

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`Container::resolve()` recurses into constructor dependencies with no in-progress tracking, so a mutual dependency (A needs B, B needs A) recurses until the stack is exhausted (fatal error) instead of failing loudly. Add an in-progress resolution set so a re-entered class id throws a loud `CircularDependencyException` naming the cycle.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Container/Container.php` (`resolve()` constructor-dependency loop ~169-204; `newInstanceArgs`)
  - New: `/Users/markshust/Sites/marko/packages/core/src/Exceptions/CircularDependencyException.php` (extends `MarkoException`, implements `Psr\Container\ContainerExceptionInterface` like `BindingException`)
  - Tests: `/Users/markshust/Sites/marko/packages/core/tests/Unit/Container/ContainerTest.php`
- Patterns to follow:
  - Maintain a private `array<string, bool> $resolving` keyed by the RESOLVED class id (the `$id` after binding/preference indirection, i.e. the concrete class being reflected at ~line 169). Check for re-entry and throw BEFORE constructing the `ReflectionClass`/recursing into dependencies, so a self-referencing class is caught too.
  - The cycle detection guard belongs ONLY around the constructor-dependency resolution branch (~169-205), NOT the early `instances`/closure-binding returns — closure bindings and pre-built instances cannot recurse through `resolve()` the same way and must not be flagged.
  - Mark `$this->resolving[$id] = true` immediately before the `foreach ($parameters ...)` dependency loop; clear it (`unset($this->resolving[$id])`) in a `finally` wrapping the dependency resolution + `newInstanceArgs`, so a mid-resolution `BindingException`/`ReflectionException` does not leave the set poisoned (the "resolve-after-failure" requirement depends on this).
  - Preserve INSERTION ORDER for the chain message: PHP arrays keyed by string preserve insertion order, so `array_keys($this->resolving)` gives the active chain; append the re-entered id to render e.g. `A -> B -> A`. Use this to build `CircularDependencyException::forChain($chain)`.
  - Re-entry check: `if (isset($this->resolving[$id])) { throw CircularDependencyException::forChain([...array_keys($this->resolving), $id]); }`. Note an already-resolved singleton returns from `$this->instances` early and never reaches this guard, so legitimate repeated resolution of a shared service is unaffected.
  - Loud-error factory with `message`/`context`/`suggestion` (mirror `BindingException::noImplementation()`).
  - Declare the new exception in the `@throws` of `resolve()`/`get()`/`call()` alongside the existing `BindingException|ReflectionException|PluginException`.
  - Use class-level fixtures (two classes with mutual constructor dependencies) defined at the top of the test file. NOTE: the container autowires concrete classes directly (no binding needed) — a fixture `class A { __construct(B $b){} }` and `class B { __construct(A $a){} }` resolved via `$container->get(A::class)` is sufficient to trigger the cycle.

## Requirements (Test Descriptions)
- [x] `it throws CircularDependencyException when two classes have mutual constructor dependencies`
- [x] `it throws CircularDependencyException for a self-referencing constructor dependency`
- [x] `it includes the dependency chain in the CircularDependencyException message`
- [x] `it still resolves a normal acyclic dependency graph`
- [x] `it can resolve a class again after a previous resolution threw an exception`
- [x] `it implements Psr ContainerExceptionInterface on CircularDependencyException`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `private array<string, bool> $resolving` to `Container` to track in-progress resolutions
- Guard is placed BEFORE the `ReflectionClass` construction so self-referencing classes are caught immediately
- Guard is placed ONLY in the constructor-dependency branch (not closure/instance early-returns)
- `finally` block wraps the entire dependency resolution + `newInstanceArgs` to clear the set on any exception
- Chain string built via `[...array_keys($this->resolving), $id]` — insertion order preserved by PHP associative arrays
- Updated `CircularDependencyException` to implement `ContainerExceptionInterface` and added `forChain()` factory method (kept existing `detected()` for backward compatibility with module loader)
- Updated `@throws` on `get()`, `call()`, and `resolve()` to include `CircularDependencyException`
