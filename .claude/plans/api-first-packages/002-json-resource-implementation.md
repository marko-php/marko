# Task 002: JsonResource Implementation

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Implement the JsonResource base class that wraps entities and transforms them into JSON-ready arrays. This is the primary way developers define how their entities appear in API responses.

## Context
- Package: `packages/api/`
- JsonResource implements ResourceInterface from task 001
- Developers extend JsonResource and override `toArray()` to define field mappings
- The wrapped entity is accessible via `$this->resource` property
- ConditionalValue and MissingValue from task 001 control field inclusion
- Study `packages/admin-api/src/Response/ApiResponse.php` for existing response patterns
- Study how blog controllers manually build response arrays — JsonResource replaces that pattern

## Requirements (Test Descriptions)
- [ ] `it wraps an entity and exposes it via the resource property`
- [ ] `it serializes entity fields to array via toArray method`
- [ ] `it includes conditional fields when condition is true`
- [ ] `it excludes conditional fields when condition is false`
- [ ] `it omits fields with MissingValue from output array`
- [ ] `it returns JSON Response via toResponse with correct content type`

## Acceptance Criteria
- All requirements have passing tests
- JsonResource is in `src/Resource/JsonResource.php`
- Supports nested resources (a resource field can be another JsonResource)
- toResponse() returns a routing Response with JSON body and application/json content type
- Code follows code standards

## Implementation Notes
