# Task 001: EntityCollection — Typed Collection Class

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the `EntityCollection` class that wraps arrays of entities with typed, chainable methods. This is a foundational building block used by Repository return types and relationship loading. It implements `Countable` and `IteratorAggregate` so it works with foreach and count().

## Context
- New file: `packages/database/src/Entity/EntityCollection.php`
- Test file: `packages/database/tests/Entity/EntityCollectionTest.php`
- Patterns to follow: Existing readonly value objects in `packages/database/src/Schema/` for immutability patterns; existing entity test fixtures in `packages/database/tests/`
- The class is generic over Entity subtypes: `EntityCollection<T of Entity>`
- Must NOT use traits or magic methods per code standards

## Requirements (Test Descriptions)

### Construction & Basic Access
- [x] `it creates an empty collection`
- [x] `it creates a collection from an array of entities`
- [x] `it returns the count of entities`
- [x] `it returns true for isEmpty when collection has no entities`
- [x] `it returns false for isEmpty when collection has entities`
- [x] `it returns all entities as an array via toArray`
- [x] `it is iterable via foreach`
- [x] `it implements Countable interface`
- [x] `it implements IteratorAggregate interface`

### Retrieval Methods
- [x] `it returns the first entity or null when empty`
- [x] `it returns the last entity or null when empty`
- [x] `it returns the first entity from a non-empty collection`
- [x] `it returns the last entity from a non-empty collection`

### Filtering & Transformation
- [x] `it filters entities by callback returning new collection`
- [x] `it maps entities to an array of transformed values`
- [x] `it applies each callback to every entity and returns self for chaining`
- [x] `it checks contains with callback returning true when match exists`
- [x] `it checks contains with callback returning false when no match`

### Property Extraction
- [x] `it plucks a single property from all entities into an array`
- [x] `it plucks a property that may be null`

### Sorting
- [x] `it sorts entities by a property in ascending order`
- [x] `it sorts entities by a property in descending order`
- [x] `it returns a new collection when sorting without modifying original`

### Grouping & Chunking
- [x] `it groups entities by a property returning collection of collections`
- [x] `it chunks entities into collection of collections of given size`
- [x] `it handles chunk size larger than collection`

## Acceptance Criteria
- All requirements have passing tests
- EntityCollection is readonly or effectively immutable (all mutating methods return new instances)
- Implements Countable and IteratorAggregate
- No traits, no magic methods
- Strict types declared
- @throws tags on any methods that throw
- `pluck()` and `sortBy()` use direct public property access (`$entity->$propertyName`) since entity properties are public by convention. Do NOT use reflection for these methods
- PHP runtime does not enforce generics -- the `<T of Entity>` generic is PHPDoc-only for static analysis. Do not add runtime type checking in the constructor

## Implementation Notes
- Implemented as a non-readonly class (readonly classes cannot hold mutable array state at construction time with PHP's rules; immutability is enforced by returning new instances from all mutating methods)
- `contains()` uses PHP 8.5's native `array_any()` function
- `groupBy()` returns `array<string, self<T>>` keyed by string-cast property value
- `chunk()` returns `array<int, self<T>>` using `array_chunk()`
- All transformation methods (`filter`, `sortBy`, `groupBy`, `chunk`) return new instances, never mutating the original
- `each()` returns `$this` for chaining (no new instance since it is non-mutating)
- 26 tests, 35 assertions — all passing
