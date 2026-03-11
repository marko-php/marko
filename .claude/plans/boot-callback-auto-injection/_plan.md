# Plan: Boot Callback Auto-Injection

## Created
2026-03-11

## Status
completed

## Objective
Add a `call()` method to the Container that invokes callables with auto-injected dependencies, then use it in `Application::boot()` so module boot callbacks can declare any registered dependency in their argument list instead of only receiving `ContainerInterface`.

## Scope

### In Scope
- Add `call(callable): mixed` to `ContainerInterface` and `Container`
- Update `Application::boot()` to use `$this->container->call($module->boot)` instead of `($module->boot)($this->container)`
- Update architecture docs to reflect the new boot callback signature

### Out of Scope
- Adding `call()` with explicit parameter overrides (future enhancement)

## Success Criteria
- [ ] `Container::call()` resolves callable parameters via the container
- [ ] Boot callbacks can declare specific dependencies (e.g., `GateInterface $gate`)
- [ ] Existing boot callbacks using `ContainerInterface $container` continue to work
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add call() method to Container | - | pending |
| 002 | Use call() in Application boot loop | 001 | pending |
| 003 | Update architecture docs | 001 | pending |

## Architecture Notes
- The `call()` method reuses the same reflection-based resolution logic as constructor autowiring, but for arbitrary callables (closures, methods)
- `ReflectionFunction` is used for closures (the primary use case for boot callbacks)
- **The container is NOT currently registered as an instance.** Task 002 must add `$this->container->instance(ContainerInterface::class, $this->container)` after container creation so boot callbacks can resolve `ContainerInterface`
- Existing boot callbacks all use untyped `function ($container)` and must be updated to `function (ContainerInterface $container)` for `call()` to resolve them
- A new `BindingException::unresolvableCallableParameter()` factory method is needed since the existing `unresolvableParameter()` says "in class" which is misleading for closures

## Risks & Mitigations
- **Callable type complexity**: Limit initial implementation to `Closure` since that's what boot callbacks use. Method callables can be added later if needed.
- **Backward compatibility**: All existing boot callbacks use untyped `$container`. These must be migrated to typed `ContainerInterface $container` as part of task 002. This is a breaking change for any third-party module.php with untyped boot callbacks.
- **Container self-registration**: The container must be registered as its own instance for `ContainerInterface` resolution. This is a prerequisite added in task 002.
