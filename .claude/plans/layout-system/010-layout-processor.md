# Task 010: LayoutProcessor — Flat Slot Rendering

**Status**: completed
**Depends on**: 006, 007, 008, 009
**Retry count**: 0

## Description
Create the `LayoutProcessor` class that orchestrates the full render pipeline for flat (non-nested) slots. This is the main entry point: given a controller class, method, and route parameters, it resolves the layout, collects components, renders each component's template with its data, assembles slot HTML, renders the layout template with slots, and returns a Response.

## Context
- Related files: `packages/routing/src/Router.php` (orchestration pattern), `packages/view/src/ViewInterface.php`
- Uses `LayoutResolver` to find the root component
- Uses `HandleResolver` to generate the page handle
- Uses `ComponentCollector` to gather matching components
- Uses `ComponentDataResolver` to invoke each component's `data()` method
- Uses `ViewInterface::renderToString()` to render each component template
- Renders the layout template with a `$slots` variable containing assembled HTML per slot name
- Returns `Response::html()` with the final rendered page

### Method Signature
`process(string $controllerClass, string $action, string $routePath, array $routeParameters, Request $request): Response`

The caller (LayoutMiddleware from Task 013) provides all needed data. The `routePath` is needed by `HandleResolver::generate()` to build the handle string. Route parameters are passed to `ComponentDataResolver` for injection into `data()` methods.

### Slot Validation
After collecting components, validate that every component's `slot` value exists in the layout component's `slots` array. If a component targets a slot not defined by the layout, throw `SlotNotFoundException`.

## Requirements (Test Descriptions)
- [ ] `it resolves layout from controller and method`
- [ ] `it generates handle from route definition`
- [ ] `it collects components for the generated handle`
- [ ] `it resolves data for each component`
- [ ] `it renders each component template with its data via ViewInterface`
- [ ] `it assembles rendered HTML into slot buckets`
- [ ] `it renders the layout template with assembled slots`
- [ ] `it returns an HTML Response with the final output`
- [ ] `it renders components in sortOrder within each slot`
- [ ] `it passes route parameters to component data resolution`
- [ ] `it throws SlotNotFoundException when component targets undefined slot`

## Acceptance Criteria
- All requirements have passing tests
- Class is readonly with constructor injection
- Uses ViewInterface for all template rendering (template-engine agnostic)
- Throws loud errors for invalid slot references
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
