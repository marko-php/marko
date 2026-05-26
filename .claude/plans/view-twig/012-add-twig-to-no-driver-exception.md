# Task 012: Add marko/view-twig to NoDriverException driver package list

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`marko/view` already defines `NoDriverException` with a `DRIVER_PACKAGES` const that lists every install candidate when no view driver is bound. The message follows the same pattern as `marko/database`'s `NoDriverException`. After adding `marko/view-twig` as a sibling driver, the const must include it so the exception's suggestion lists every available driver:

```
Install a view driver:
- `composer require marko/view-latte`
- `composer require marko/view-twig`
```

This is a one-line addition to the const array.

## Context
- Related files:
  - `packages/view/src/Exceptions/NoDriverException.php` (modify — add `'marko/view-twig'` to `DRIVER_PACKAGES` const)
  - `packages/view/tests/Exceptions/NoDriverExceptionTest.php` (update — assert both drivers appear in the suggestion text)
- Reference pattern: `packages/database/src/Exceptions/NoDriverException.php` lists both `marko/database-mysql` and `marko/database-pgsql` in its `DRIVER_PACKAGES` const — same shape this task produces for view
- Keep alphabetical order in the const (`marko/view-latte` before `marko/view-twig`)
- Out of scope: actually wiring the exception to be thrown by the container — that is a pre-existing gap in both `marko/view` and `marko/database` (neither currently throws this exception from a fallback binding). Tracked separately; not blocking this plan.

## Requirements (Test Descriptions)
- [ ] `it lists marko/view-latte as an installable driver`
- [ ] `it lists marko/view-twig as an installable driver`
- [ ] `it formats each driver as a composer require command in the suggestion`
- [ ] `it keeps DRIVER_PACKAGES alphabetically ordered`

## Acceptance Criteria
- `NoDriverException::DRIVER_PACKAGES` contains both `'marko/view-latte'` and `'marko/view-twig'` in alphabetical order
- The exception's `suggestion` text includes both `composer require` commands
- Existing `NoDriverExceptionTest` assertions updated to cover the new entry
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
