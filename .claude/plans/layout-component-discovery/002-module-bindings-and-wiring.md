# Task 002: Module Bindings and LayoutProcessor Wiring

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add all layout system bindings to `layout/module.php`. This includes binding `ComponentCollectorInterface` to `DiscoveringComponentCollector`, `LayoutProcessorInterface` to `LayoutProcessor`, and registering `HandleResolver` and `LayoutResolver` as singletons.

No changes to `LayoutProcessor` are needed. The `[]` first argument to `collect()` is semantically correct -- it means "no additional classes beyond what was discovered." The `DiscoveringComponentCollector::collect()` merges that empty array with its auto-discovered classes.

## Context
- Related files: `packages/layout/module.php` (add bindings), `packages/layout/src/DiscoveringComponentCollector.php` (from task 001)
- The `module.php` `bindings` array maps interface to implementation class strings; `singletons` array maps shared instances
- The current `module.php` is empty (`'bindings' => []`) -- ALL bindings need to be added in this task
- No changes to `LayoutProcessor` source code are needed
- Bindings to add:
  - `bindings`: `ComponentCollectorInterface` -> `DiscoveringComponentCollector`, `LayoutProcessorInterface` -> `LayoutProcessor`
  - `singletons`: `HandleResolver`, `LayoutResolver`

## Requirements (Test Descriptions)
- [ ] `it binds ComponentCollectorInterface to DiscoveringComponentCollector in module.php`
- [ ] `it binds HandleResolver as a singleton in module.php`
- [ ] `it binds LayoutResolver as a singleton in module.php`
- [ ] `it binds LayoutProcessorInterface to LayoutProcessor in module.php`

## Acceptance Criteria
- All requirements have passing tests
- `module.php` has correct bindings for all layout system interfaces
- Container resolves `ComponentCollectorInterface` to `DiscoveringComponentCollector`
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
