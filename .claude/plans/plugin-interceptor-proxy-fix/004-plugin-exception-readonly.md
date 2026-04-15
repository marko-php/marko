# Task 004: PluginException for Readonly and Ambiguous Interface Targets

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Add two new static factory methods to `PluginException`: (1) for when a plugin directly targets a readonly concrete class (PHP prohibits extending readonly classes), and (2) for when a resolved concrete class implements multiple interfaces that each have plugins registered (ambiguous — Marko is loud about this rather than silently picking a winner).

## Context
- Related files:
  - `packages/core/src/Exceptions/PluginException.php` — file to modify
  - `packages/core/tests/Unit/Plugin/PluginExceptionTest.php` or inline tests — add tests
- Follow the existing pattern of static factory methods (`cannotTargetPlugin`, `conflictingSortOrder`, etc.)
- The `MarkoException` base class takes `message`, `context`, and `suggestion` params

## Requirements (Test Descriptions)

### cannotInterceptReadonly
- [ ] `it creates cannotInterceptReadonly exception with class name in message`
- [ ] `it includes suggestion to target the interface instead in cannotInterceptReadonly`
- [ ] `it creates cannotInterceptReadonly exception that is instance of PluginException`

### ambiguousInterfacePlugins
- [ ] `it creates ambiguousInterfacePlugins exception listing the conflicting interfaces`
- [ ] `it includes suggestion to target the concrete class directly in ambiguousInterfacePlugins`
- [ ] `it creates ambiguousInterfacePlugins exception that is instance of PluginException`

## Acceptance Criteria
- All requirements have passing tests
- New `cannotInterceptReadonly(string $targetClass)` static method on `PluginException`
- New `ambiguousInterfacePlugins(string $concreteClass, array $interfaces)` static method on `PluginException`
- Readonly message explains that readonly classes cannot be intercepted; suggestion guides to interface targeting
- Ambiguous message lists the conflicting interfaces; suggestion guides to targeting the concrete class directly
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
