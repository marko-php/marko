# Task 006: preg_quote literal route segments and consistent param decoding

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`RouteDefinition::buildRegex()` substitutes `{param}` placeholders then wraps the raw path in `#^...$#` without escaping literal text. So `/sitemap.xml` matches `/sitemapXxml` (the `.` is a regex wildcard) and a `#` in a path breaks the `#` delimiter. Escape literal path segments with `preg_quote` while preserving `{param}` placeholder substitution, and decode matched params consistently with `Request::path()`.

## Context
- Related files: `packages/routing/src/RouteDefinition.php` (extractParameters ~31-37, buildRegex ~39-45 — `preg_replace` then `'#^' . $pattern . '$#'`), `packages/routing/src/Http/Request.php` (`path()` ~48-54 — returns the raw, un-decoded URI path), `packages/routing/src/RouteMatcher.php` (`match()` runs `preg_match($route->regex, $normalizedPath)` ~28; `extractParameters()` ~56-69 copies named-group matches verbatim — this is the single decode insertion point)
- Patterns to follow: split the path on `{param}` placeholders, `preg_quote(..., '#')` each literal chunk so `#` inside a literal is escaped and cannot break the `#...#` delimiter, then re-insert `(?P<name>[^/]+)` named groups; keep the `#` delimiter (escaping a literal `#` via `preg_quote` is sufficient — no need to change delimiter). The matcher matches against the raw `Request::path()`, so `rawurldecode` the matched values in exactly one place — `RouteMatcher::extractParameters()` (~63) — to decode once and avoid double-decoding; `Request::path()` stays un-decoded; keep `extractParameters()` (on `RouteDefinition`, the placeholder-name extractor) behavior unchanged.
- **No file collision with Task 005:** Task 005 edits `Router.php` only; this task edits `RouteDefinition.php` + `RouteMatcher.php`. Both reference `Request::path()` but neither modifies it. Safe to run in parallel.

## Requirements (Test Descriptions)
- [ ] `it matches a literal dot in a path segment only against a real dot`
- [ ] `it does not match a path where a literal dot position contains a different character`
- [ ] `it matches a path containing a hash character without breaking the regex delimiter`
- [ ] `it still captures a named parameter for a route with a {param} placeholder`
- [ ] `it matches a path containing other regex metacharacters literally`
- [ ] `it rawurldecodes a matched parameter value exactly once`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
