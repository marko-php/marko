# Task 034: Update `ScopeWalker` to Accept `HasScopesInterface`

**Status**: complete
**Depends on**: [033]
**Retry count**: 0

## Description
Change `ScopeWalker::walk()` and `ScopeWalker::walkAt()` to accept `HasScopesInterface` instead of the concrete `ScopedOverridesEntity`. This makes the walker work with both the companion-class approach and the new trait-based approach without any conditional logic.

## Context
- Related files:
  - `packages/scope/src/Resolution/ScopeWalker.php` — change param type on both methods; swap `use Marko\Scope\Storage\ScopedOverridesEntity;` for `use Marko\Scope\Storage\HasScopesInterface;`
  - `packages/scope/tests/Unit/Resolution/` — keep existing tests (they pass `ScopedOverridesEntity` subclass instances, which now implement the interface) and add tests with a trait-based fixture (entity that `use HasScopes` + `implements HasScopesInterface`)
- The walker only calls `hasOverride()` and `getOverride()` on the storage object — both are declared on `HasScopesInterface`. The body of both methods is unchanged; only the type signature changes.
- Existing tests that pass a `ScopedOverridesEntity` continue to pass because `ScopedOverridesEntity implements HasScopesInterface` (task 033).
- Downstream callers: `ScopeResolver::resolved()` and `ScopeResolver::resolvedAt()` currently pass a `ScopedOverridesEntity` companion into `walk()/walkAt()`. After widening to `HasScopesInterface` these calls still compile because `ScopedOverridesEntity implements HasScopesInterface`. Verify the existing `ScopeResolver` test suite continues passing without modification.

## Requirements (Test Descriptions)
- [x] `it resolves an override via walk when passed a HasScopesInterface implementor that is not ScopedOverridesEntity`
- [x] `it returns notFound via walk when the HasScopesInterface implementor has no matching override`
- [x] `it resolves an override via walkAt when passed a HasScopesInterface implementor`
- [x] `it returns notFound via walkAt when the axis does not match`
- [x] `it walks hierarchy ancestors when the exact scope path has no override`

## Acceptance Criteria
- All requirements have passing tests
- Both `walk()` and `walkAt()` parameter types changed to `HasScopesInterface`
- The `Marko\Scope\Storage\ScopedOverridesEntity` import is replaced with `Marko\Scope\Storage\HasScopesInterface` in `ScopeWalker.php`
- No conditional logic added — pure type-widening change
- Existing `ScopeWalker` tests continue passing
- Existing `ScopeResolver` tests continue passing without modification (verifies the resolver still compiles against the widened signature)
- Code follows project standards

## Implementation Notes
- Changed `ScopeWalker::walk()` and `ScopeWalker::walkAt()` parameter type from `ScopedOverridesEntity` to `HasScopesInterface`
- Replaced `use Marko\Scope\Storage\ScopedOverridesEntity;` with `use Marko\Scope\Storage\HasScopesInterface;` in `ScopeWalker.php`
- Added `WalkerTraitProduct` fixture (Entity + HasScopes trait + HasScopesInterface) to `ScopeWalkerTest.php`
- No conditional logic added — pure type-widening change; body of both methods is unchanged
- All existing tests (ScopeWalker + ScopeResolver) continue passing without modification
