# Task 045: Wire docs-vec DI binding

**Status**: pending
**Depends on**: 044
**Retry count**: 0

## Description
Wire `VecSearch` as the DI implementation of `DocsSearchInterface` in the package's `module.php`. Since users install either docs-fts or docs-vec (not both), composer's `replace` or DI conflict detection ensures only one driver is active.

## Context
- File: `packages/docs-vec/module.php`
- Singleton binding
- Use `Marko\DocsVec\VecSearch` as the bound class

## Requirements (Test Descriptions)
- [ ] `it registers DocsSearchInterface singleton binding to VecSearch in module.php`
- [ ] `it resolves to VecSearch from the Marko container when docs-vec is installed`
- [ ] `it throws BindingConflictException if both docs-fts and docs-vec are installed without explicit replace`

## Acceptance Criteria
- Integration test boots a Marko app with docs-vec and resolves the interface
- Conflict detection works if both drivers present

## Implementation Notes
(Filled in by programmer during implementation)
