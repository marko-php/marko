# Task 028: Implement validate_module MCP tool

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Implement `validate_module` — given a module name (or path), run a suite of consistency checks and return structured diagnostics: missing required files, invalid attribute usage, unresolvable Preferences, duplicate sortOrders on conflicting plugins, circular dependency hints.

## Context
- Namespace: `Marko\Mcp\Tools\ValidateModuleTool`
- Input: `{ module: string }`
- Output: array of `{ severity, message, file, line, suggestion }`

## Requirements (Test Descriptions)
- [ ] `it registers validate_module tool`
- [ ] `it flags missing composer.json in target module`
- [ ] `it flags duplicate Before plugin sortOrders targeting the same method`
- [ ] `it flags Preferences pointing to non-existent classes`
- [ ] `it returns empty diagnostics for a valid module`
- [ ] `it includes file and line for every finding`
- [ ] `it suggests a fix for each diagnostic`

## Acceptance Criteria
- Fixtures cover each diagnostic class
- Output format parseable as structured data by AI agents

## Implementation Notes
(Filled in by programmer during implementation)
