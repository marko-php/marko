# Task 004: HandleResolver

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `HandleResolver` class that auto-generates handle strings from route path, controller class name, and action method name. Also resolves class-reference handles (`[Controller::class, 'method']`) to their string form, and implements prefix matching logic.

## Context
- Related files: `packages/routing/src/RouteDefinition.php` (has path, controller, action)
- Handle convention: `{first-route-segment}_{controller-minus-suffix}_{method}`, all lowercased
- First segment of route path (e.g., `/products/{id}` → `products`, `/` → `index`)
- Controller class minus `Controller` suffix (e.g., `ProductController` → `product`). Uses short class name (after last `\`).
- Method name as-is (e.g., `show`, `index`)
- Prefix matching: `'customer'` matches `customer_order_show`, `customer_order_index`, etc.
- `'default'` is a special handle that matches everything

### Method Signatures
- `generate(string $path, string $controllerClass, string $action): string` -- generates handle from primitives (not RouteDefinition). Takes the route path, FQCN of controller, and action method name.
- `matches(string $componentHandle, string $pageHandle): bool` -- returns true if the component handle matches the page handle. Handles `'default'` (matches everything), prefix matching (component handle is a prefix of page handle), and exact matching.
- Class-reference resolution (`[Controller::class, 'method']` to handle string) requires knowing the route path for that controller. This resolution happens in `ComponentCollector` (Task 007) which has access to `RouteCollection` -- NOT in HandleResolver. HandleResolver only works with already-resolved string handles.

## Requirements (Test Descriptions)
- [ ] `it generates handle from simple route and controller`
- [ ] `it uses first path segment as route portion`
- [ ] `it uses index as route portion for root path`
- [ ] `it strips Controller suffix from class name and lowercases`
- [ ] `it uses method name as action portion`
- [ ] `it resolves class reference array to handle string`
- [ ] `it determines that default handle matches any handle`
- [ ] `it determines that a prefix matches handles starting with that prefix`
- [ ] `it determines that a full handle only matches itself exactly`
- [ ] `it determines that a non-matching prefix does not match`
- [ ] `it handles multi-segment routes using only first segment`
- [ ] `it handles controller names without Controller suffix gracefully`

## Acceptance Criteria
- All requirements have passing tests
- Class is readonly
- Uses constructor property promotion where applicable
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
