# Task 005: ComponentDefinition

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create the `ComponentDefinition` readonly data class that holds a resolved component's metadata. This is the runtime representation of a discovered `#[Component]` — it combines the attribute metadata with the resolved class name. Similar in pattern to `RouteDefinition`.

## Context
- Related files: `packages/routing/src/RouteDefinition.php` (pattern to follow)
- Created by `ComponentCollector` when scanning `#[Component]` attributes
- Holds: `className`, `template`, `slot`, `handles` (resolved to string array), `slots`, `sortOrder`, `before`, `after`
- `handles` are stored as resolved string handles (class references already converted)

## Requirements (Test Descriptions)
- [ ] `it stores component class name`
- [ ] `it stores template from attribute`
- [ ] `it stores slot from attribute`
- [ ] `it stores resolved handle strings`
- [ ] `it stores slots array for nested composition`
- [ ] `it stores sortOrder defaulting to 0`
- [ ] `it stores before and after class references`
- [ ] `it can determine if it has a data method via reflection`
- [ ] `it can determine if it defines sub-slots`

## Acceptance Criteria
- All requirements have passing tests
- Class is readonly
- Uses constructor property promotion
- Follows RouteDefinition pattern
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
