# Task 004: Entity — extension bag, `extension()`, `setExtension()`

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Modify the `Entity` base class to carry a private extension bag and expose two methods: `setExtension()` for the hydrator to attach extension instances, and `extension()` — a `@template`-typed generic accessor — for consumers to retrieve typed extension instances. This is the only change to `Entity`.

## Context
- Related files: `packages/database/src/Entity/Entity.php`
- The extension bag is `private array $extensions = []` keyed by extension class name (`class-string<EntityExtension>`)
- Both methods MUST be `public` — `setExtension()` is called from `EntityHydrator` (task 006) and `Repository` consumers; `extension()` is the read accessor for consumers
- `extension()` must use `@template T of EntityExtension` and `@param class-string<T>` / `@return T|null` so that PhpStorm, Psalm, and PHPStan all resolve the return type from the argument. The concrete return type hint is `?EntityExtension`
- `setExtension()` accepts `EntityExtension $extension` and stores it keyed by `$extension::class`
- Calling `setExtension()` twice with the same class overwrites the previous instance (last write wins)
- `Entity` is not currently abstract-with-no-properties on purpose; the existing `EntityTest` instantiates an anonymous subclass with declared public properties — the added `$extensions` private property must not collide with any existing public property name. Verify the new property name `extensions` is not already declared on any entity in the repo before finalising. (Quick grep: search for `public.*\$extensions` across `packages/*/src/Entity/`.)
- Tests for `Entity` currently only cover that it is abstract; add new tests in a separate test class or file rather than modifying existing tests if they would break

## Requirements (Test Descriptions)
- [ ] `it returns null for an extension that has not been set`
- [ ] `it returns the extension instance after it is set`
- [ ] `it overwrites a previously set extension of the same class`
- [ ] `it stores multiple extensions independently by class`

## Acceptance Criteria
- All requirements have passing tests
- IDE type inference: `$entity->extension(MyExtension::class)` resolves to `MyExtension|null` via `@template`
- Code follows project standards
