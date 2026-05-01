# Task 024: Implement list_modules, list_commands, list_routes MCP tools

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Implement three read-only discovery tools that let an AI agent enumerate the project structure: `list_modules`, `list_commands`, `list_routes`. All three pull from `IndexCache`.

## Context
- Namespace: `Marko\Mcp\Tools\ListModulesTool`, `ListCommandsTool`, `ListRoutesTool`
- Input schema per tool: optional filter strings (substring match)
- Output: JSON lists appropriate to each tool

## Requirements (Test Descriptions)
- [ ] `it registers list_modules tool returning IndexCache::getModules output`
- [ ] `it registers list_commands tool returning all CommandEntry records with name, class, module`
- [ ] `it registers list_routes tool returning all RouteEntry records with method, path, handler`
- [ ] `it supports optional substring filter on each tool`
- [ ] `it returns empty arrays when filter matches nothing`
- [ ] `it includes source file paths so the agent can open them`

## Acceptance Criteria
- All three tools appear in `tools/list`
- Fixtures with multiple modules + commands + routes exercise the tools

## Implementation Notes
(Filled in by programmer during implementation)
