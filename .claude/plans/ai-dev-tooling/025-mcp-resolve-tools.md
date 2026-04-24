# Task 025: Implement resolve_preference, resolve_template MCP tools

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Implement `resolve_preference` (maps an interface or class to its active Preference binding) and `resolve_template` (maps a `module::template` string to its absolute file path). Both pull from IndexCache.

## Context
- Namespace: `Marko\Mcp\Tools\ResolvePreferenceTool`, `ResolveTemplateTool`
- `resolve_preference` input: `{ class: string }`
- `resolve_template` input: `{ template: string }` e.g. `"blog::posts/index"`

## Requirements (Test Descriptions)
- [ ] `it registers resolve_preference tool`
- [ ] `it resolves an interface to its bound implementation when a preference exists`
- [ ] `it resolves a class to its #[Preference]-annotated replacement when one exists`
- [ ] `it returns null when no preference or binding exists`
- [ ] `it registers resolve_template tool`
- [ ] `it returns the absolute file path for a valid module::template`
- [ ] `it returns a structured not-found error including searched paths`

## Acceptance Criteria
- Both tools appear in `tools/list`
- Results include enough metadata for goto-definition (file, line)

## Implementation Notes
(Filled in by programmer during implementation)
