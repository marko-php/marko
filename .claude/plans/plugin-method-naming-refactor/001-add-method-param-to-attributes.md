# Task 001: Add `method` Parameter to Before/After Attributes

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add an optional `method` parameter to the `#[Before]` and `#[After]` attribute classes. When provided, this parameter specifies the target method name explicitly, allowing the plugin method to be named anything. When omitted, the plugin method name IS the target method name.

## Context
- Related files: `packages/core/src/Attributes/Before.php`, `packages/core/src/Attributes/After.php`
- Test file: `packages/core/tests/Unit/Plugin/PluginAttributesTest.php`
- Both attribute classes currently have only `sortOrder` parameter

## Requirements (Test Descriptions)
- [ ] `it creates Before attribute with optional method parameter`
- [ ] `it creates After attribute with optional method parameter`
- [ ] `it defaults method to null when not specified on Before`
- [ ] `it defaults method to null when not specified on After`
- [ ] `it creates Before attribute with both method and sortOrder parameters`
- [ ] `it creates After attribute with both method and sortOrder parameters`

## Acceptance Criteria
- All requirements have passing tests
- Existing attribute tests still pass (updated for new constructor signatures)
- Code follows code standards
