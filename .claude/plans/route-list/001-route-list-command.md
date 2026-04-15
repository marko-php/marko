# Task 001: RouteListCommand Basic Table Output

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `RouteListCommand` that injects `RouteCollection` and displays all registered routes in an aligned table with columns: METHOD, PATH, ACTION, MIDDLEWARE. Routes are sorted by path then method. Shows a helpful message when no routes exist.

## Context
- Related files:
  - `packages/routing/src/Commands/RouteListCommand.php` (new)
  - `packages/routing/tests/Commands/RouteListCommandTest.php` (new — this directory does not exist yet, create it)
  - `packages/core/src/Commands/ModuleListCommand.php` (pattern to follow — note: empty state handling is a divergence; ModuleListCommand does not handle empty collections)
  - `packages/core/tests/Command/ModuleListCommandTest.php` (test pattern to follow)
  - `packages/routing/src/RouteCollection.php` (injected dependency)
  - `packages/routing/src/RouteDefinition.php` (route data)
  - `packages/core/src/Command/CommandInterface.php` (interface to implement)
  - `packages/core/src/Attributes/Command.php` (attribute to use)
- Patterns to follow:
  - `ModuleListCommand` for table output with `str_pad()` alignment
  - `ModuleListCommandTest` for test structure using `php://memory` stream
  - Constructor property promotion, `declare(strict_types=1)`, no `final`, `readonly class`
  - ACTION column uses short class name (`UserController::index`), not FQCN — strip namespace
  - No separator line between header and data (consistent with `ModuleListCommand`)

## Requirements (Test Descriptions)
- [ ] `it has Command attribute with name route:list`
- [ ] `it has Command attribute with description Show all registered routes`
- [ ] `it implements CommandInterface`
- [ ] `it displays METHOD column header`
- [ ] `it displays PATH column header`
- [ ] `it displays ACTION column header`
- [ ] `it displays MIDDLEWARE column header`
- [ ] `it displays route method path and action for each route`
- [ ] `it displays middleware as short class names`
- [ ] `it sorts routes by path then by method`
- [ ] `it displays No routes registered when collection is empty`
- [ ] `it displays empty middleware column when route has no middleware`
- [ ] `it formats output with aligned columns`
- [ ] `it returns exit code 0`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards (phpcs/php-cs-fixer clean)
- `RouteListCommand` lives in `Marko\Routing\Commands` namespace

## Implementation Notes
(Left blank - filled in by programmer during implementation)
