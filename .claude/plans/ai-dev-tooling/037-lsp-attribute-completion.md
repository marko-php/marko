# Task 037: Implement LSP custom attribute parameter completion

**Status**: pending
**Depends on**: 033
**Retry count**: 0

## Description
Implement parameter-aware completion inside Marko framework attributes: `#[Observer(event: |)]` suggests only event classes; `#[Plugin(target: |)]` suggests only class references; `#[Command(name: |)]` validates name format; `#[Get('|')]`/`#[Post('|')]`/etc. offer route path param hints.

## Context
- Namespace: `Marko\Lsp\Features\AttributeFeature`
- Detection: AST-aware — recognizes attribute context by class name
- Data source: `IndexCache` (class graph, command names, route paths)

## Requirements (Test Descriptions)
- [ ] `it offers event classes in Observer event parameter completion`
- [ ] `it offers class references in Plugin target parameter completion`
- [ ] `it validates Command name format and flags invalid names as diagnostic`
- [ ] `it offers middleware class names in Route middleware array completion`
- [ ] `it offers DisableRoute with zero parameters`
- [ ] `it does not offer completion outside of Marko attributes`

## Acceptance Criteria
- Fixtures cover each attribute type
- Completion filters correctly by partial typed text

## Implementation Notes
(Filled in by programmer during implementation)
