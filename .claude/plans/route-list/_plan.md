# Plan: route:list CLI Command

## Created
2026-04-12

## Status
completed

## Objective
Add a `route:list` CLI command to the `marko/routing` package that displays all registered routes in a formatted table, with filtering options for method and path.

## Related Issues
none

## Scope

### In Scope
- `RouteListCommand` class in `packages/routing/src/Commands/`
- Table output: METHOD, PATH, ACTION (controller::method), MIDDLEWARE
- `--method=GET` filter (case-insensitive)
- `--path=users` filter (substring match, leading slash optional)
- Sorted output (by path, then method)
- Empty state message when no routes match
- Routes sorted alphabetically by path for scanability

### Out of Scope
- Disabled route display (omitted = not routable, intuitive)
- Override/Preference metadata display (no plumbing exists, not needed for v1)
- Route conflict detection (already caught at boot time with loud errors)
- `--verbose` mode
- `route:cache` or `route:clear` (future commands)

## Success Criteria
- [ ] `marko route:list` displays all active routes in aligned columns
- [ ] `--method=GET` filters to only GET routes
- [ ] `--path=users` filters to routes containing "users" in path
- [ ] Filters can be combined
- [ ] Empty collection shows helpful message
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | RouteListCommand basic table output | - | pending |
| 002 | Method and path filtering | 001 | pending |

## Architecture Notes
- Command lives in `packages/routing/src/Commands/RouteListCommand.php` (routing package owns its own commands)
- Inject `RouteCollection` — already available in container after routing boots
- Follow `ModuleListCommand` pattern: `str_pad()` alignment, `Output::writeLine()`, `php://memory` stream in tests
- Use `#[Command(name: 'route:list', description: 'Show all registered routes')]`
- Sort routes by path then method for consistent, scannable output
- `--method` filter uses `RouteCollection::byMethod()` (already exists), case-insensitive via `strtoupper()`
- `--path` filter uses `str_contains()` substring match, strips leading slash for convenience
- Middleware displayed as comma-separated short class names (last segment of FQCN)

## Risks & Mitigations
- **RouteCollection not in container**: Command runs after boot, so routes are already discovered. If somehow empty, show "No routes registered." message.
- **Long middleware lists breaking alignment**: Use short class names (strip namespace) to keep columns manageable.
- **ACTION column format**: Use short class name (`UserController::index`), not FQCN. Future `--verbose` flag can show full namespaces.
- **No separator line**: Consistent with `ModuleListCommand` pattern. No header/data separator row.
- **readonly class**: `RouteListCommand` should be `readonly` — single immutable dependency.
