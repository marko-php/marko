# Task 020: Update Observer Attribute

**Status**: completed
**Depends on**: -
**Retry count**: 0

## Description
Add async parameter to the Observer attribute in marko/core.

## Context
- Add `async` boolean parameter to Observer attribute
- When async=true, observer will be queued for later processing
- Default is false for backward compatibility

## Requirements (Test Descriptions)
- [ ] `Observer attribute accepts async parameter`
- [ ] `Observer async defaults to false`
- [ ] `Observer stores async value`

## Implementation Notes
- Modify packages/core/src/Attributes/Observer.php
