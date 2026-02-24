# Task 001: PaginatorInterface, CursorInterface, CursorPaginatorInterface, PaginationException

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Create the pagination package scaffolding and define the core contracts: `PaginatorInterface` for offset-based pagination, `CursorPaginatorInterface` for cursor-based pagination, `CursorInterface` for cursor value objects, and `PaginationException` with three-part error pattern. Also implement the `Cursor` value object since it is needed by both paginator implementations.

## Context
- Namespace: `Marko\Pagination\`
- Package: `marko/pagination`
- Dependencies: marko/core (for MarkoException base class)
- `PaginatorInterface` is the primary contract for offset-based pagination
- `CursorPaginatorInterface` is a separate contract for cursor-based pagination (not extending PaginatorInterface, since cursor pagination has no concept of total/currentPage/lastPage)
- `CursorInterface` defines the encode/decode contract for cursor value objects
- `Cursor` is a readonly value object that holds column/value parameters and encodes to URL-safe base64 JSON
- `PaginationException` follows three-part pattern (message, context, suggestion) with static factory methods
- All interfaces go in `src/Contracts/`
- Exception goes in `src/Exceptions/`
- Cursor value object goes in `src/Cursor.php`

## Requirements (Test Descriptions)
- [ ] `it defines PaginatorInterface with items, total, perPage, currentPage, lastPage, hasMorePages, previousPage, nextPage, toArray methods`
- [ ] `it defines CursorPaginatorInterface with items, perPage, hasMorePages, cursor, nextCursor, previousCursor, toArray methods`
- [ ] `it defines CursorInterface with parameters, parameter, encode methods and decode static factory`
- [ ] `it creates Cursor value object from column/value parameters`
- [ ] `it encodes Cursor to URL-safe base64 string and decodes back`
- [ ] `it throws PaginationException for invalid cursor with message, context, and suggestion`
- [ ] `it throws PaginationException for invalid page number with helpful message`

## Acceptance Criteria
- PaginatorInterface defines all eight accessor methods plus toArray()
- CursorPaginatorInterface defines cursor-specific methods plus toArray()
- CursorInterface defines encode/decode contract
- Cursor encodes parameters to base64-encoded JSON, decodes back to original parameters
- Cursor::decode() throws PaginationException for malformed strings
- PaginationException extends MarkoException with static factory methods (invalidPage, invalidPerPage, invalidCursor)
- All files have strict_types, proper namespacing, no final classes

## Implementation Notes
(Left blank)
