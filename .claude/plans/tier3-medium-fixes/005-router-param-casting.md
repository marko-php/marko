# Task 005: Router casts POST/query scalars and fails loudly for missing required params

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`Router::resolveParameters()` casts only route params through `castToType()`; POST and query values are injected raw, and a missing required typed param is injected as `null`. A typed scalar action parameter (`int $page`, `bool $active`) therefore raises a raw `TypeError` rather than a helpful response. Apply `castToType()` to POST/query values too, and produce a clear 4xx response (not a raw `TypeError`) when a required typed param is missing or cannot be cast.

## Context
- Related files: `packages/routing/src/Router.php` (resolveParameters ~77-115, priority block ~100-111, castToType ~117-131, wrapResult ~133-149, handle ~37-68), `packages/routing/src/Http/Request.php` (`query()` ~59, `post()` ~73)
- Patterns to follow: route params already cast via `castToType()` — mirror for POST (`$postValue`) and query (`$queryValue`); return a deliberate 4xx `Response` (e.g. 400/422), keeping routing decoupled from the validation package (no `marko/validation` types); loud message naming the missing/invalid parameter; only intervene for typed scalar params with no default (untyped or defaulted params keep current behavior).

### Control-flow note (VERIFIED at review time — do not miss)
`resolveParameters()` returns `array<mixed>` and is called from inside the `$handler` closure in `handle()` (~46-59), which then invokes the controller. A bare `Response` cannot be `return`ed from `resolveParameters()` because its caller immediately spreads the result into the controller call. The loud-4xx path therefore must thread the failure back through `handle()`:
- Preferred: introduce a routing-owned exception (e.g. `InvalidRouteParameterException extends MarkoException`, in `packages/routing/src/Exceptions/`) thrown from `resolveParameters()` when a required typed scalar param is missing/uncastable, caught in `handle()` (or the handler closure) and mapped to a 400/422 `Response`. This keeps routing decoupled from `marko/validation`.
- Add the new exception to `handle()`'s `@throws` only if it propagates; if it is caught-and-converted to a `Response` inside `handle()`, it does not propagate.
- The existing 404 path (`new Response('Not Found', 404)`) is the precedent for returning a deliberate HTTP Response from the router.
- `bool` casting is lossy: `(bool) "0"` is `false` but `(bool) "false"` is `true`. Tests for bool casting must assert against the actual `(bool)` cast semantics (`"1"`/`"0"` style), not English `"true"`/`"false"` strings, so the cast is loud and predictable rather than surprising.

## Requirements (Test Descriptions)
- [x] `it casts a POST value to an int action parameter`
- [x] `it casts a POST value to a bool action parameter`
- [x] `it casts a query-string value to a typed scalar action parameter`
- [x] `it returns a 4xx response naming the parameter when a required typed scalar param is missing`
- [x] `it does not raise a TypeError when a required typed scalar param is missing`
- [x] `it still injects the default value for an optional param that has one`
- [x] `it prefers a route param over a POST value of the same name`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Applied `castToType()` to both POST (`$postValue`) and query (`$queryValue`) scalars in `resolveParameters()` — mirrors existing route param behavior.
- Created `packages/routing/src/Exceptions/InvalidRouteParameterException.php` with a `missingRequired()` factory method.
- Added `isRequiredTypedScalar()` helper: returns true for non-nullable `int|float|bool|string` typed params.
- For missing required typed scalar params, `resolveParameters()` throws `InvalidRouteParameterException`; the handler closure in `handle()` catches it and returns `new Response($e->getMessage(), 400)` — no TypeError propagation.
- Untyped params and params with defaults keep existing behavior unchanged.
- 7 new tests added to `packages/routing/tests/RouterTest.php`; all 23 RouterTest tests pass.
