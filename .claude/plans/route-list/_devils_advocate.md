# Devil's Advocate Review: route-list

## Critical (Must fix before building)

No critical issues found. The plan is well-scoped and aligns correctly with existing patterns.

## Important (Should fix before building)

### 1. `byMethod()` does strict equality -- case-insensitivity must happen BEFORE calling it (Task 002)

The plan says to use `strtoupper()` for case-insensitivity, which is correct. However, the task description says "Method filter is case-insensitive and uses the existing `RouteCollection::byMethod()`" without explicitly noting that `strtoupper()` must be applied to the user input BEFORE passing to `byMethod()`, since `byMethod()` does a strict `===` comparison (`$route->method === $method`). Routes are stored as uppercase ('GET', 'POST', etc.) per the attribute classes.

This is implied but should be explicit in the task requirements to prevent the worker from passing the raw user input directly to `byMethod()`.

**Fix:** Add explicit note in task 002 that `strtoupper()` must be applied to the `--method` option value before passing to `byMethod()`.

### 2. Test file path inconsistency with existing routing test structure (Tasks 001, 002)

The plan places tests at `packages/routing/tests/Commands/RouteListCommandTest.php`. Looking at the existing routing test files, tests are NOT organized in subdirectories matching `src/` -- they are flat in `tests/` (e.g., `tests/RouteCollectionTest.php`, `tests/RouteDefinitionTest.php`). The only subdirectories are `Attributes/`, `Exceptions/`, `Http/`, `Integration/`, `Middleware/`, and `Fixtures/`.

Meanwhile, the pattern being followed (`ModuleListCommand`) has its test at `packages/core/tests/Command/ModuleListCommandTest.php` -- note `Command/` (singular), not `Commands/` (plural), while the source is in `Commands/` (plural).

The routing package does not have a `Commands/` test directory. Either path convention works, but the task should be explicit about which to use.

**Fix:** Keep `tests/Commands/` to match the source namespace `src/Commands/`. This is fine -- the routing package just hasn't had command tests before. Note this in task 001 context.

### 3. Missing test for routes with no middleware (Task 001)

The requirements test middleware display as "short class names" but don't explicitly cover the case where a route has an empty middleware array (`middleware = []` is the default per `RouteDefinition`). The column should show nothing (empty string) rather than crashing.

**Fix:** Add requirement to task 001.

### 4. Missing test for the `--path` filter's leading slash handling (Task 002)

The requirement says "it strips leading slash from path filter before matching" but needs clarification: should it strip the leading slash from the filter input, from the route path, or both? Route paths in `RouteDefinition` start with `/` (e.g., `/users/{id}`). If the user passes `--path=/users`, stripping the leading slash from the filter gives `users`, and then `str_contains('/users/{id}', 'users')` works. But if the user passes `--path=users`, it also works without stripping. The stripping only matters for the filter input, not the route path.

**Fix:** Clarify in task 002 that the leading slash is stripped only from the filter value, not from route paths. Route paths always start with `/`.

## Minor (Nice to address)

### 1. ModuleListCommand has no empty-state handling

The plan references `ModuleListCommand` as the pattern to follow, but `ModuleListCommand` does NOT handle the empty collection case -- it just prints headers with no rows. Task 001 correctly adds "No routes registered." for the empty case, which is good, but the worker should know this is a divergence from the exact `ModuleListCommand` pattern.

### 2. Consider `readonly class` for RouteListCommand

Per code standards, if all constructor-promoted properties are readonly, use `readonly class`. Since `RouteListCommand` will only have `private RouteCollection $routeCollection`, the class should likely be `readonly class RouteListCommand`. The worker should be aware of this standard.

### 3. Action column format not specified

The plan says "ACTION (controller::method)" but doesn't specify whether to show the FQCN or just the short class name. For consistency with the middleware column (short class names), it might make sense to use short class names for the controller too. However, using FQCN provides more useful information for debugging. This is a design choice the plan should make explicitly.

## Questions for the Team

1. **Action column format**: Should the ACTION column show the full namespace (`App\Http\Controllers\UserController::index`) or short class name (`UserController::index`)? Full namespace is more useful for debugging but makes the table wider.

2. **Separator line**: Should there be a separator line (e.g., dashes) between the header and data rows? `ModuleListCommand` doesn't have one, but route tables in other frameworks (Laravel's `route:list`, for example) typically do.
