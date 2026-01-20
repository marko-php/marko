# Task 005: Dependency Resolution and Module Loading

**Status**: pending
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
- [ ] `it sorts modules with no dependencies in discovery order`
- [ ] `it loads required modules before dependents`
- [ ] `it respects sequence after hints for load ordering`
- [ ] `it respects sequence before hints for load ordering`
- [ ] `it throws CircularDependencyException when modules have circular require`
- [ ] `it includes cycle path in CircularDependencyException message`
- [ ] `it throws ModuleException when required module is not found`
- [ ] `it filters out disabled modules from load order`
- [ ] `it throws ModuleException when enabled module requires disabled module`
- [ ] `it handles complex dependency graphs correctly`
- [ ] `it returns ModuleManifest objects in final load order`

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
(Left blank - filled in by programmer during implementation)
