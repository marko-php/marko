# Task 026: Implement find_event_observers, find_plugins_targeting MCP tools

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Implement inverse-index discovery tools: `find_event_observers` (given an event class, find all observers that react) and `find_plugins_targeting` (given a target class, find all Plugin classes that intercept it). These are the Marko-specific framework-semantic queries generic PHP LSPs cannot answer.

## Context
- Namespace: `Marko\Mcp\Tools\FindEventObserversTool`, `FindPluginsTargetingTool`
- Input schemas accept FQCN strings
- Output includes observer/plugin class, file path, line, priority/sortOrder

## Requirements (Test Descriptions)
- [ ] `it registers find_event_observers tool`
- [ ] `it returns all observer classes listening to a given event class`
- [ ] `it includes observer priority in results`
- [ ] `it returns empty list for events with no observers`
- [ ] `it registers find_plugins_targeting tool`
- [ ] `it returns all plugin classes targeting a given class with their Before/After methods and sortOrders`
- [ ] `it returns empty list for classes with no plugins`

## Acceptance Criteria
- Fixtures include real attribute-annotated observer and plugin classes
- Results sorted by priority/sortOrder ascending

## Implementation Notes
(Filled in by programmer during implementation)
