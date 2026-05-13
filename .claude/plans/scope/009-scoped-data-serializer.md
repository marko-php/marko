# Task 009: `ScopedDataSerializer`

**Status**: complete
**Depends on**: 002, 004
**Retry count**: 0

## Description
JSON serialization/deserialization for the `scopes` column. Implements the agreed shape: `{"axis:path": {"property": value, ...}, ...}`. Handles type conversion for `BackedEnum` and `DateTimeImmutable` so saved JSON is portable.

## Context
- Related files: `packages/scope/src/Storage/ScopedDataSerializer.php` (new)
- Patterns to follow: `Marko\Database\Repository\Repository::convertToDbValue()` for the BackedEnum/DateTime conversion pattern.

## Data Shape Contract
The canonical override map is keyed by scope key first, property second:

```php
[
    'geo:eu.de'   => ['name' => 'Hemd', 'price' => 19.99],
    'locale:de'   => ['name' => 'Hallo'],
]
```

This shape is shared with `ScopedOverridesEntity` (task 010), `ScopeWalker` (task 011), `ScopedEntityValidator` (task 013), and the SQL renderers (tasks 015, 019, 023). The serializer round-trips this map; the JSON column stores `null` when the map is empty.

## Requirements (Test Descriptions)
- [x] `it serializes an empty overrides map to null rather than empty object`
- [x] `it serializes nested overrides keyed by axis colon path`
- [x] `it deserializes valid JSON into a flat array structure`
- [x] `it round-trips BackedEnum values via their backing scalar`
- [x] `it round-trips DateTimeImmutable values via formatted string`
- [x] `it throws ScopeConfigurationException when deserialized JSON is malformed`
- [x] `it deserializes null or empty string into an empty overrides map`

## Acceptance Criteria
- Uses `json_encode`/`json_decode` with `JSON_THROW_ON_ERROR` flag; converts to `ScopeConfigurationException`.
- Stateless — `readonly class` with no dependencies, or a final-free static-like utility.
- Round-trip: `deserialize(serialize($x))` returns equivalent structure for `$x`.

## Implementation Notes
- `ScopedDataSerializer` is a `readonly class` with no constructor dependencies.
- `serialize()` converts `BackedEnum` via `.value` and `DateTimeImmutable` via `format('Y-m-d H:i:s')` before calling `json_encode(..., JSON_THROW_ON_ERROR)`. Returns `null` for empty map.
- `deserialize()` returns `[]` for `null`/`''` input; wraps `JsonException` in `ScopeConfigurationException`.
- PHP's `json_encode` natively serializes `BackedEnum` to its backing scalar, but explicit conversion is used for clarity and consistency with the Database `convertToDbValue()` pattern.
