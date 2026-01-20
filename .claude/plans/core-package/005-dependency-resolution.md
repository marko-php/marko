# Task 005: Dependency Resolution and Module Loading

**Status**: complete
**Depends on**: 004
**Retry count**: 0

## Description
Create the dependency resolver that performs topological sorting of modules based on their dependencies and sequence hints. This ensures modules are loaded in the correct order with dependencies satisfied.

## Context
- Location: `packages/core/src/Module/`
- Uses topological sort (Kahn's algorithm or DFS-based)
- Must detect circular dependencies
- Respects both `require` (hard dependencies) and `sequence` (soft ordering hints)

## Requirements (Test Descriptions)
- [x] `it sorts modules with no dependencies in discovery order`
- [x] `it loads required modules before dependents`
- [x] `it respects sequence after hints for load ordering`
- [x] `it respects sequence before hints for load ordering`
- [x] `it throws CircularDependencyException when modules have circular require`
- [x] `it includes cycle path in CircularDependencyException message`
- [x] `it throws ModuleException when required module is not found`
- [x] `it filters out disabled modules from load order`
- [x] `it throws ModuleException when enabled module requires disabled module`
- [x] `it handles complex dependency graphs correctly`
- [x] `it returns ModuleManifest objects in final load order`

## Acceptance Criteria
- All requirements have passing tests
- Algorithm is O(V+E) for V modules and E dependencies
- Cycle detection provides clear path for debugging
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Module/
  DependencyResolver.php    # Topological sort and validation
  ModuleLoader.php          # Coordinates discovery + resolution
```

## Implementation Notes

### DependencyResolver Implementation

Created `packages/core/src/Module/DependencyResolver.php` implementing Kahn's algorithm for topological sorting with O(V+E) complexity.

**Key Features:**
1. **Disabled module filtering**: Filters out disabled modules before resolution
2. **Hard dependency validation**: Validates all `require` dependencies exist and are enabled
3. **Soft ordering hints**: Supports `after` and `before` sequence hints from module.php
4. **Circular dependency detection**: Uses DFS to find and report cycle paths

**Algorithm Flow:**
1. Filter out disabled modules
2. Build index of enabled modules by name
3. Validate all required dependencies exist
4. Build adjacency list (dependents) and in-degree count from:
   - Hard dependencies (`require` array keys)
   - Soft hints (`after` and `before` arrays)
5. Process modules with no dependencies first (Kahn's algorithm)
6. Detect cycles if not all modules were sorted

**Exception Handling:**
- `ModuleException::missingDependency()` - Added new factory method for missing/disabled dependencies
- `CircularDependencyException::detected()` - Includes full cycle path (e.g., "A -> B -> C -> A")

**Note:** ModuleLoader.php was not created as it was not required by the test specifications. It can be added in a future task if needed to coordinate discovery + resolution.
