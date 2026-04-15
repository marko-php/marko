# Devil's Advocate Review: layout-system

## Critical (Must fix before building)

### C1. Router is `readonly` -- Plugin interception is impossible (Task 013)

`Router` is declared as `readonly class Router` at `packages/routing/src/Router.php:19`. The `InterceptorClassGenerator::generateConcreteSubclassCode()` at line 123 explicitly checks `$reflection->isReadOnly()` and throws `PluginException::cannotInterceptReadonly()`. There is no `RouterInterface` either, so the interface wrapper strategy also cannot apply.

Task 013 says "Uses Plugin on Router (not modifying Router source)" -- this will fail at runtime.

**Fix:** Task 013 must use a different integration strategy. Options:
1. Add an `#[After]` plugin on the **controller's action method result** rather than the Router itself -- but this is per-controller, not global.
2. Create a middleware that checks for `#[Layout]` and delegates to `LayoutProcessor`, bypassing the normal `wrapResult()` flow. The controller returns void or data, the middleware intercepts and renders the layout.
3. Modify `Router` to not be readonly (removes `readonly` keyword) so plugins can work -- but this changes routing package.

The middleware approach is most aligned with the decoupling goal. The layout middleware would run as the innermost middleware, check for `#[Layout]` on the matched controller, and if found, invoke `LayoutProcessor` instead of letting the default `wrapResult()` handle the response.

### C2. `ComponentCollection.move()` mutates a `readonly` `ComponentDefinition` (Tasks 005, 006)

Task 006 says `move()` can move a component to a different slot with a new sortOrder. But Task 005 says `ComponentDefinition` is `readonly`. You cannot change `slot` or `sortOrder` on a readonly object. The worker building Task 006 will be blocked.

**Fix:** Either:
- `move()` should remove the old definition and add a new one with modified slot/sortOrder (using `clone() with` from PHP 8.5)
- Or document that `move()` replaces the entry in the internal collection with a cloned definition

Task 006 must specify that `move()` uses `clone($definition) with { slot: $newSlot, sortOrder: $newSortOrder }` internally.

### C3. `resolveParameters` and `castToType` are `private` on Router -- cannot be reused (Task 009)

Task 009 says "Reuses same resolution logic pattern as Router (not duplicated -- extracted or mirrored)" but `Router::resolveParameters()` and `Router::castToType()` are both `private`. There is no shared utility to call. The acceptance criteria says "not duplicated" but the only option is to duplicate or extract.

**Fix:** Task 009 must either:
- Extract a `ParameterResolver` utility class as a sub-task (adds a dependency on modifying/adding to the routing package)
- Or acknowledge that the logic will be mirrored (copy the pattern) since it's small (~40 lines). Update acceptance criteria to say "mirrors the same resolution logic pattern as Router" instead of "not duplicated."

The mirroring approach is simpler and avoids changing the routing package. Also note: `ComponentDataResolver` only needs route parameters and Request injection -- it does NOT need POST/query fallback since components are not form handlers. The task should clarify this scoping difference.

## Important (Should fix before building)

### I1. `HandleResolver` needs `RouteDefinition` data but Task 004 has no dependency on routing types (Task 004)

Task 004 says it generates handles from "route path, controller class name, and action method name" and references `RouteDefinition`. But it lists no dependencies. The worker needs to know the exact method signature. Should `HandleResolver::generate()` take a `RouteDefinition`? Or separate string params? The `resolveFromClassReference` method needs to turn `[Controller::class, 'method']` into a handle, which requires knowing the route path for that controller -- meaning it needs access to `RouteCollection` to look up the route.

**Fix:** Task 004 should:
- Specify that `generate(string $path, string $controller, string $action): string` takes primitives (not RouteDefinition) for the basic handle generation
- Specify that `resolveClassReference(string $controllerClass, string $method, RouteCollection $routes): string` needs RouteCollection to find the path
- Or specify that class-reference handle resolution happens at collection time in `ComponentCollector` (Task 007), not in `HandleResolver`
- Add `matches(string $componentHandle, string $pageHandle): bool` as the prefix-matching method signature

### I2. `ComponentCollector` discovery mechanism is underspecified (Task 007)

`RouteDiscovery::discoverInModule()` currently returns `[]` (empty stub at line 27). The collector pattern referenced doesn't actually work yet. Task 007 says "Follows RouteDiscovery pattern" but doesn't specify how classes are actually discovered -- does it scan the filesystem? Get a list of classes from `ModuleManifest`? Receive pre-registered class names?

**Fix:** Task 007 should specify how classes are provided to the collector. The simplest approach: `ComponentCollector` receives an array of class-strings (e.g., from module boot callbacks or a class scanner) and scans them for `#[Component]` attributes. The method signature should be explicit: `collect(array $classNames, string $handle): ComponentCollection`.

### I3. Task 012 Latte extension targets `view-latte` package, not `layout` package (Task 012)

Task 012 modifies `packages/view-latte/` (a separate package). This creates a cross-package dependency during development. The `{slot}` tag is described as living in `view-latte`, but:
- The `layout` package's `composer.json` (Task 001) doesn't require `view-latte`
- `view-latte` doesn't currently require `layout`
- Who owns the extension class?

**Fix:** The Latte slot extension should live in the `layout` package and be registered conditionally when `view-latte` is installed. Or it should be in a separate `layout-latte` bridge package. The simplest approach: the `{slot}` tag is just a template helper that reads from the `$slots` variable already passed to the template -- it doesn't need any layout package classes. It's purely a Latte convenience. So it can live in `view-latte` with no dependency on `layout`. Task 012 should clarify this: the tag just outputs `$slots[$name] ?? ''` and has zero imports from `Marko\Layout`.

### I4. `LayoutProcessor` needs the matched route's controller/action/parameters, but the integration path is unclear (Tasks 010, 013)

`LayoutProcessor::process()` needs: controller class, method name, route parameters, and the Request. But the plan doesn't specify how these are passed. If using middleware (per C1 fix), the middleware needs access to the matched route info. Currently `RouteMatcher::match()` is called inside `Router::handle()` and the `MatchedRoute` is only available in a local variable/closure.

**Fix:** Task 013 must address how the matched route data flows to the layout system. Options:
- Store `MatchedRoute` on the Request object (add a method)
- Have the middleware re-match the route (wasteful)
- Refactor Router to expose matched route data

This should be specified in Task 013's requirements.

### I5. `Component` attribute `handle` type is complex and needs exact type definition (Task 002)

The `handle` parameter accepts: `string` (prefix/default), `array` as `[Controller::class, 'method']` (class reference), or `array` of mixed handles. This creates ambiguity -- is `['customer', 'admin']` two prefix handles, or a malformed class reference? The type `string|array` is too loose.

**Fix:** Task 002 should define the exact type and validation:
- `string` = prefix handle or `'default'`
- `array{0: class-string, 1: string}` = single class reference (exactly 2 elements, first is class-string)
- `array<string|array{0: class-string, 1: string}>` = multiple handles
- Validation: if array has exactly 2 elements and first element is a valid class name, treat as class reference. Otherwise treat as array of handles.

### I6. No test for what happens when controller returns data AND has `#[Layout]` (Task 013)

Task 013 mentions "controller may return void or data" but doesn't specify what happens with the returned data. If a controller returns `['products' => $products]`, should that data be available to components? Or is it ignored? The plan's render pipeline (steps 1-9 in `_plan.md`) doesn't mention controller return values at all.

**Fix:** Task 013 should specify: when a controller has `#[Layout]`, the controller's return value is ignored (components get their own data via `data()` methods). The controller action is still called (for side effects like authorization checks), but its return value is not used. Add a test: `it ignores controller return value when Layout is present`.

### I7. Slot validation timing is inconsistent between Tasks 010 and 011

Task 010 says `it throws SlotNotFoundException when component targets undefined slot`. Task 011 says `circular references detected at collection time, not render time`. But for Task 010 (flat slots), when does slot validation happen? The layout component defines its available slots in the `slots` parameter of `#[Component]`. But this validation requires knowing which slots exist on the layout -- which means `LayoutProcessor` needs to read the layout component's `slots` array and validate all collected components' `slot` values against it.

**Fix:** Task 010 should specify: after collecting components and resolving the layout, validate that every component's `slot` value exists in the layout component's `slots` array (for top-level) or in a parent component's `slots` array (for nested, Task 011). The layout component's `#[Component]` attribute must declare its available slots.

## Minor (Nice to address)

### M1. `before`/`after` sorting in ComponentCollection is a topological sort problem (Task 006)

The `before`/`after` constraints combined with `sortOrder` create a constraint satisfaction problem. Simple numeric sorting won't respect `before`/`after` if sort orders conflict. Task 006 doesn't specify what happens when `sortOrder` contradicts `before`/`after` (e.g., component A has `sortOrder: 10, before: B` but B has `sortOrder: 5`). Should `before`/`after` override `sortOrder`? This needs a clear precedence rule.

### M2. Handle convention for nested route segments is ambiguous (Task 004)

The convention says "first route segment" -- for `/api/v2/products/{id}`, is the handle `api_product_show`? What about `/` (root) -- the plan says `index` but what about `/about` with no controller suffix match? These edge cases should have explicit test cases.

### M3. `ComponentDefinition.hasDataMethod()` uses reflection at runtime (Task 005)

Task 005 says the definition "can determine if it has a data method via reflection." If this is called per-component per-request, that's a reflection call in a hot path. Consider resolving this at collection/discovery time and storing it as a boolean on the definition.

### M4. No `CircularSlotException` in the exception list (Task 001)

Task 011 requires detecting circular slot references and throwing an error, but Task 001's exception list doesn't include a `CircularSlotException` or similar. It would need to be added to the scaffolding or thrown as a generic `SlotNotFoundException`.

## Questions for the Team

### Q1. Should `marko/layout` require `marko/view-latte`, or should the Latte integration be optional?
The current plan has the `{slot}` tag in `view-latte`, but the layout package itself renders via `ViewInterface` (engine-agnostic). If someone uses a different template engine, do they need to implement their own slot tag? The `$slots` variable approach means templates can just use `{$slots['content']}` directly without a custom tag.

### Q2. Should the layout package create a `LayoutProcessorInterface`?
Following the interface/implementation split pattern from the architecture, should there be a `LayoutProcessorInterface` that other packages can type-hint against? Or is this an internal implementation detail?

### Q3. What is the expected behavior when multiple `#[Component]` classes target the same slot with the same sortOrder and no before/after constraints?
Is the order deterministic (alphabetical by class name)? Random? Should this be a loud error?

### Q4. The `Component` attribute has both `slot` (where this component renders) and `slots` (sub-slots this component provides). Is this naming confusing?
Would `provides` or `childSlots` be clearer than `slots` for the sub-slot definition?
