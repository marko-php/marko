# Task 001: Add call() Method to Container

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a `call()` method to `ContainerInterface` and `Container` that invokes a callable with auto-resolved dependencies. This uses the same reflection-based resolution as constructor autowiring but applied to closure/function parameters.

## Context
- Related files: `packages/core/src/Container/ContainerInterface.php`, `packages/core/src/Container/Container.php`, `packages/core/tests/Unit/Container/ContainerTest.php`, `packages/core/src/Exceptions/BindingException.php`
- Patterns to follow: The existing `resolve()` method's parameter resolution logic (lines 108-144 of Container.php)
- The method should use `ReflectionFunction` for closures to inspect parameters
- The existing `BindingException::unresolvableParameter()` says "in class" which is misleading for closures. Add a new factory method `unresolvableCallableParameter(string $parameter)` with message "Cannot resolve parameter '$x' in callable" and suggestion "Add a class or interface type hint to the parameter"
- Nullable typed parameters (e.g., `?FooInterface $foo`) should resolve to `null` when the type is not bound in the container, rather than throwing

## Requirements (Test Descriptions)
- [ ] `it calls a closure with no parameters`
- [ ] `it resolves typed parameters from the container when calling a closure`
- [ ] `it resolves multiple parameters from the container`
- [ ] `it uses default values for scalar parameters in callables`
- [ ] `it throws BindingException for unresolvable callable parameters` (verify the error message uses "callable" not "class" -- uses new `BindingException::unresolvableCallableParameter()`)
- [ ] `it returns the callable return value`
- [ ] `it resolves nullable typed parameters to null when not bound in container`

## Acceptance Criteria
- All requirements have passing tests
- `call()` is declared on `ContainerInterface` and implemented in `Container`
- Code follows code standards
