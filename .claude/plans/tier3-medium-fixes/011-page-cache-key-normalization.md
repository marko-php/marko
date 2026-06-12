# Task 011: Single canonical query normalization for page-cache store and purge

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`CacheKey::fromRequest()` builds the query string with `http_build_query($queryArray)` (default RFC1738, `+` for spaces) while `CacheKey::normalizeQuery()` pre-encodes `+` to `%2B` and uses `PHP_QUERY_RFC3986` (`%20` for spaces). The two produce different strings, so a URL with a space or `+` hashes differently on store than on purge and can never be purged. Use one canonical query-normalization routine for both the store path and the purge path.

## Context
- Related files: `packages/page-cache/src/CacheKey.php` (fromRequest ~17-27 uses `http_build_query`, normalizeQuery ~29-40 uses RFC3986 + `%2B` pre-encode, hash ~42-45), `packages/routing/src/Http/Request.php` (`query()`), page-cache purge command/service that calls `normalizeQuery`
- Patterns to follow: extract a single private/static normalization helper that both `fromRequest()` and `normalizeQuery()` call so store and purge are byte-identical; pick one canonical encoding (consistent space/`+` handling and `ksort`) and apply it in both paths; keep the `method|path|query` hash composition.

## Requirements (Test Descriptions)
- [x] `it produces the same cache key hash for a URL with a space whether stored from a request or normalized for purge`
- [x] `it produces the same cache key hash for a URL with a literal plus whether stored or normalized for purge`
- [x] `it sorts query parameters so key ordering does not affect the hash`
- [x] `it normalizes an empty query to an empty string in both store and purge paths`
- [x] `it produces matching hashes for a multi-parameter query across store and purge`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Extracted a private `buildQuery(array $queryArray): string` method in `CacheKey` that `ksort`s and calls `http_build_query` with `PHP_QUERY_RFC3986`.
- Both `fromRequest()` and `normalizeQuery()` now delegate to `buildQuery()` — one canonical routine for both store and purge paths.
- Removed the `str_replace('+', '%2B', ...)` pre-encode in `normalizeQuery()`: since `+` in a raw HTTP query string means space (standard HTML form encoding), `parse_str` correctly decodes it to a space, and `buildQuery` then encodes it as `%20` (RFC3986) — matching what `fromRequest()` does when PHP populates `$_GET`.
- Updated the pre-existing test `preserves query parameter values verbatim during normalization` (which expected the old `%2B` behavior) to reflect the correct canonical behavior: both `+` and `%20` in raw query strings now normalize to `%20`.
