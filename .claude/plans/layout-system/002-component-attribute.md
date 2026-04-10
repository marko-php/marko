# Task 002: Component Attribute

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `#[Component]` PHP attribute class. This is the core metadata mechanism — it holds template, slot, handle, slots, sortOrder, before, and after parameters. It targets classes only.

## Context
- Related files: `packages/core/src/Attributes/Plugin.php`, `packages/core/src/Attributes/Observer.php` (patterns to follow)
- The attribute lives in `packages/layout/src/Attributes/Component.php`
- Namespace: `Marko\Layout\Attributes`
- `handle` accepts three forms: class-reference array `[Controller::class, 'method']`, string for prefix matching (`'customer'`), or `'default'` for all pages. Also accepts an array of these for multiple handles.
- `slots` defines named sub-slots for nested composition (dot-notation)
- `before` and `after` accept class-string references to other component classes

### Handle Type Disambiguation
The `handle` parameter type is `string|array`. Disambiguation rules:
- `string` = prefix handle or `'default'` (e.g., `'customer'`, `'default'`, `'products_product_show'`)
- `array` with exactly 2 elements where the first is a class-string = single class reference (e.g., `[ProductController::class, 'show']`)
- `array` of strings/arrays = multiple handles (e.g., `['customer', 'admin']` or `[[ProductController::class, 'show'], 'default']`)
- To distinguish: if `array` has exactly 2 string elements AND the first element contains `\` (namespace separator, indicating a class name), treat as class reference. Otherwise treat as array of handles. This is resolved at collection time by `ComponentCollector`, not by the attribute itself -- the attribute just stores the raw value.

## Requirements (Test Descriptions)
- [ ] `it can be instantiated with only template parameter`
- [ ] `it can be instantiated with all parameters`
- [ ] `it accepts a string handle for prefix matching`
- [ ] `it accepts an array handle with controller class and method`
- [ ] `it accepts an array of handles for multiple page targeting`
- [ ] `it accepts a slots array for nested composition`
- [ ] `it defaults sortOrder to 0`
- [ ] `it defaults handle to default`
- [ ] `it defaults slot to null`
- [ ] `it defaults slots to empty array`
- [ ] `it defaults before and after to null`
- [ ] `it targets classes only`

## Acceptance Criteria
- All requirements have passing tests
- Attribute is readonly
- Uses constructor property promotion
- Follows existing attribute patterns (Plugin, Observer)
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
