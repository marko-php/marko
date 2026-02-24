# Task 003: CursorPaginator with Cursor Encoding/Decoding

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Implement `CursorPaginator` -- cursor-based (keyset) pagination for efficient API pagination. Unlike offset pagination, cursor pagination does not need a total count and does not suffer from shifting results when new records are inserted. It uses opaque cursor tokens to track position in the result set.

## Context
- Class: `Marko\Pagination\CursorPaginator`
- Implements `CursorPaginatorInterface`
- Constructor takes: items array, perPage, current cursor (nullable), next cursor (nullable), previous cursor (nullable)
- hasMorePages() is determined by whether nextCursor is non-null
- Cursors are `Cursor` value objects that encode to base64 strings
- The paginator does not know about totals or page numbers -- it only knows about the current page of results and adjacent cursors
- Common pattern: fetch perPage + 1 items, if you got more than perPage there are more pages, use last item's key as next cursor
- Validates perPage >= 1

## Requirements (Test Descriptions)
- [ ] `it creates CursorPaginator with items, perPage, and cursor objects`
- [ ] `it returns hasMorePages true when nextCursor exists`
- [ ] `it returns hasMorePages false when nextCursor is null`
- [ ] `it returns current cursor, next cursor, and previous cursor`
- [ ] `it handles first page with null current cursor and null previous cursor`
- [ ] `it handles last page with null next cursor`
- [ ] `it throws PaginationException for invalid perPage value`

## Acceptance Criteria
- CursorPaginator implements CursorPaginatorInterface
- Constructor uses property promotion
- items() returns the items array
- perPage() returns the per-page count
- hasMorePages() returns true only when nextCursor is not null
- cursor() returns the current cursor (null on first page)
- nextCursor() returns the next cursor (null on last page)
- previousCursor() returns the previous cursor (null on first page)
- Throws PaginationException::invalidPerPage() for perPage < 1
- Works correctly with empty items array (end of results)

## Implementation Notes
(Left blank)
