# Task 004: toArray() Serialization for API Responses

**Status**: done
**Depends on**: 002, 003
**Retry count**: 0

## Description
Implement the `toArray()` method on both `OffsetPaginator` and `CursorPaginator` for API serialization. Each paginator produces a structured array with items, meta, and links sections suitable for JSON API responses.

## Context
- Both paginators need a `toArray()` method returning `array{items: array, meta: array, links: array}`
- OffsetPaginator meta includes: total, per_page, current_page, last_page
- OffsetPaginator links includes: previous (page number or null), next (page number or null)
- CursorPaginator meta includes: per_page, has_more (boolean)
- CursorPaginator links includes: previous (encoded cursor string or null), next (encoded cursor string or null)
- The format is designed for direct JSON encoding in API controllers
- Links use page numbers for offset and encoded cursor strings for cursor pagination

## Requirements (Test Descriptions)
- [ ] `it serializes OffsetPaginator to array with items, meta, and links`
- [ ] `it includes total, per_page, current_page, last_page in offset meta`
- [ ] `it includes previous and next page numbers in offset links`
- [ ] `it serializes CursorPaginator to array with items, meta, and links`
- [ ] `it includes per_page and has_more in cursor meta`
- [ ] `it includes encoded cursor strings in cursor links`
- [ ] `it returns null links when no previous or next page exists`

## Acceptance Criteria
- OffsetPaginator::toArray() returns array with three keys: items, meta, links
- meta contains: total (int), per_page (int), current_page (int), last_page (int)
- links contains: previous (?int), next (?int)
- CursorPaginator::toArray() returns array with three keys: items, meta, links
- meta contains: per_page (int), has_more (bool)
- links contains: previous (?string encoded cursor), next (?string encoded cursor)
- Null cursors produce null link values
- Items are passed through as-is (no transformation)

## Implementation Notes
(Left blank)
