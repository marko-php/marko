# Plan: Before Plugin Argument Modification

## Created
2026-03-25

## Status
completed

## Objective
Enable before plugins to modify method arguments by returning an array. This aligns Marko's plugin system with Magento's proven pattern and makes the existing documentation accurate (docs already claim this works).

## Scope

### In Scope
- Modify PluginProxy to handle array returns as argument modification
- Create PluginArgumentCountException with helpful error messages (plugin name, target method, expected vs actual count)
- Add comprehensive tests for the new behavior
- Update all documentation referencing before plugins
- Fix the core.md example that already shows argument modification but doesn't work
- Fix the blog README that uses outdated method naming convention
- Update architecture.md with accurate before plugin behavior
- Update MarkoTalk's MarkdownPlugin if it benefits from argument modification
- Update YouTube script at ~/Desktop/YOUTUBE.md

### Out of Scope
- Around plugins (intentionally not supported)
- Edge case where target method returns an array and someone wants to short-circuit with an array value
- Changes to After plugin behavior

## Success Criteria
- [ ] Before plugins returning an array modify arguments for subsequent plugins and target method
- [ ] Before plugins returning null still pass through (unchanged)
- [ ] Before plugins returning non-null non-array still short-circuit (unchanged)
- [ ] Wrong argument count throws PluginArgumentCountException with helpful message
- [ ] All existing tests still pass
- [ ] New tests cover argument modification, chaining, and interaction with after plugins
- [ ] All docs accurately describe the three return behaviors
- [ ] Code follows project standards (strict types, constructor promotion, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Core implementation — modify PluginProxy and add tests | - | completed |
| 002 | Update docs and READMEs | 001 | completed |
| 003 | Update MarkoTalk and YouTube script | 001 | completed |

## Architecture Notes
- The change is in `packages/core/src/Plugin/PluginProxy.php` lines 38-46
- When a before plugin returns an array, reassign `$arguments` so subsequent plugins and target method receive modified args
- The `is_array()` check must come before the `!== null` short-circuit check
- After plugins already receive `...$arguments` — they will automatically get modified args since `$arguments` is reassigned

## Risks & Mitigations
- **Risk**: Existing before plugins that return arrays for short-circuit will now be treated as argument modification
  - **Mitigation**: No existing tests or code return arrays from before plugins. The blog/core doc examples that show returns are non-array values.
- **Risk**: Before plugin returns empty array or wrong-length array
  - **Mitigation**: Custom `PluginArgumentCountException` catches this before PHP does, providing a helpful message with the plugin class, target method, and expected vs actual argument count. Aligns with "loud errors" principle.
- **Risk**: Doc examples have return types (`?float`, `null`) that are incompatible with returning arrays
  - **Mitigation**: Task 002 explicitly requires updating return types alongside return values in all doc examples.
- **Risk**: MarkoTalk MarkdownPlugin mutates the Message object in-place rather than using argument modification
  - **Mitigation**: This still works fine — mutation is valid. We'll evaluate whether argument modification is cleaner but won't force a change.
