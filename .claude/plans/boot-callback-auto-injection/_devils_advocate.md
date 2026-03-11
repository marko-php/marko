# Devil's Advocate Review: boot-callback-auto-injection

## Critical (Must fix before building)

### C1: ContainerInterface is not registered in the container (tasks 001, 002)

The plan states "the container itself is resolvable (it's registered as an instance)" and "existing `function (ContainerInterface $container)` callbacks work without changes." This is **false**.

Looking at `Application::boot()`, the container is never registered via `$this->container->instance(ContainerInterface::class, $this->container)`. The current code works only because it directly passes the container as an argument: `($module->boot)($this->container)`.

When `call()` tries to resolve a `ContainerInterface` parameter via reflection, it will call `resolve(ContainerInterface::class)`, which will throw `BindingException::noImplementation()` because `ContainerInterface` is an interface with no binding.

**Fix:** Task 002 must register the container as an instance of both `ContainerInterface` and `Container` before the boot loop runs. Add `$this->container->instance(ContainerInterface::class, $this->container)` to `Application::boot()` right after the container is created.

### C2: Existing boot callbacks use untyped `$container` -- will break with call() (tasks 001, 002)

All three real module.php files (`errors-simple`, `errors-advanced`, `notification`) and both existing tests use `function ($container)` with **no type declaration**. The `call()` method using `ReflectionFunction` will see an untyped parameter, enter the "no type or builtin" branch, find no default value, and throw `BindingException`.

This means **all existing boot callbacks will break** the moment task 002 is deployed, directly contradicting the success criterion "existing boot callbacks using `ContainerInterface $container` continue to work."

**Fix:** Task 001's `call()` implementation must handle untyped/unresolvable parameters with no default gracefully. Two options:
1. Allow passing explicit parameter overrides to `call()` so `Application::boot()` can pass the container as a fallback for position 0. But this was explicitly scoped out.
2. Better: `call()` should accept an optional `array $parameters = []` for explicit overrides (keyed by parameter name), and `Application::boot()` should pass `['container' => $this->container]`. This solves the backward compat problem.

Actually, the cleanest fix: since boot callbacks are closures that historically receive the container, `Application::boot()` should register the container instance (fix C1), and the existing module.php files should be updated to add type hints. The plan should include a task to update the three existing module.php boot callbacks to use typed parameters.

### C3: BindingException::unresolvableParameter message says "in class" -- misleading for closures (task 001)

`BindingException::unresolvableParameter()` takes `(string $parameter, string $class)` and produces the message "Cannot resolve parameter '$x' in class '$class'". When called from `call()` on a closure, there is no class -- the error message would say something like "in class ''" or require passing a meaningless string.

**Fix:** Task 001 must add a new factory method to `BindingException`, e.g. `unresolvableCallableParameter(string $parameter)`, with an appropriate message like "Cannot resolve parameter '$x' in callable" with suggestion "Add a class or interface type hint to the parameter."

## Important (Should fix before building)

### I1: Missing test for nullable typed parameters in call() (task 001)

The constructor resolution logic in `Container::resolve()` does not handle nullable parameters (it doesn't check `$parameter->allowsNull()`). The `call()` method should decide: should a nullable typed parameter resolve to `null` if the type isn't in the container, or should it throw? This needs an explicit test and design decision.

**Fix:** Add a test requirement to task 001: `it resolves nullable typed parameters to null when not bound in container`.

### I2: Existing module.php files need updating (task 002)

Three real module.php files use untyped `function ($container)`. Even with C1 fixed (ContainerInterface registered), these closures have **no type hint**, so `call()` still cannot resolve them. These files must be updated to `function (ContainerInterface $container)`.

**Fix:** Task 002 must explicitly list updating these files:
- `packages/errors-simple/module.php`
- `packages/errors-advanced/module.php`
- `packages/notification/module.php`

And the existing ApplicationTest boot callback tests must be updated to use typed parameters.

### I3: Existing ApplicationTest boot callback tests will break (task 002)

The two existing tests at lines 1185 and 1239 of `ApplicationTest.php` create module.php files with `function ($container)` (untyped). After task 002 changes `Application::boot()` to use `call()`, these tests will fail because `call()` cannot resolve untyped parameters.

**Fix:** Task 002 requirements must include updating these existing tests to use typed parameters, or at minimum acknowledge they need updating.

### I4: ModuleDiscoveryTest boot callback test uses untyped parameter (task 002)

Line 586 of `ModuleDiscoveryTest.php` also has `function ($container)`. While this test may not go through `call()` (it tests discovery, not boot execution), the docs and examples should be consistent.

### I5: Task 003 references architecture.md line numbers that may shift (task 003)

Task 003 references "lines 516-533" of architecture.md. After tasks 001-002 modify source files, the architecture.md lines won't shift, but the task should reference content patterns rather than fragile line numbers.

### I6: README files reference untyped boot callbacks (task 003)

Multiple README files show `function ($container)` examples:
- `packages/core/README.md` (lines 126, 143)
- `packages/scheduler/README.md` (line 25)
- `packages/cache-redis/README.md` (line 43)
- `packages/mail-log/README.md` (line 39)
- `packages/health/README.md` (line 127 -- this one already shows typed params!)

Task 003 should update README examples too, not just architecture.md.

## Minor (Nice to address)

### M1: call() could support invokable objects and static methods

The plan limits `call()` to closures only (`ReflectionFunction`). This is fine for the boot callback use case, but if `call()` is added to the public `ContainerInterface`, consumers may expect it to handle `[object, method]` or invokable objects. Consider documenting the closure-only limitation in the docblock.

### M2: Return type of call() is `mixed`

The interface declares `call(callable): mixed` but boot callbacks return void. This is fine for now but the `mixed` return type is broader than the narrowest possible type (code standard #7). Since `call()` is general-purpose, `mixed` is appropriate here.

### M3: No test for call() with a closure that has union type parameters

Union types like `Foo|Bar` would hit `ReflectionNamedType` checks differently (`ReflectionUnionType`). This edge case isn't covered but is unlikely to appear in boot callbacks.

## Questions for the Team

### Q1: Should `call()` support explicit parameter overrides?

The plan explicitly scopes this out, but it would be useful for passing request-scoped data. If we add `array $parameters = []` now, it avoids a breaking interface change later. The `notification/module.php` boot callback uses `$container->has()` to conditionally resolve -- would auto-injection change that pattern?

### Q2: Should untyped parameters in `call()` fall back to the container itself?

This would provide perfect backward compatibility without updating existing module.php files. But it's "magic" behavior that contradicts the framework's explicit-over-implicit principle.
