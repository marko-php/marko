# Plan: ORM — Relationships, Collections & Query Specifications

## Created
2026-04-14

## Status
completed

## Objective
Extend the existing Data Mapper database layer with explicit relationship loading, typed entity collections, and composable query specifications — building a relationship-aware ORM that stays true to Marko's "no magic, no proxies, no lazy loading" philosophy.

## Related Issues
none

## Scope

### In Scope
- Relationship attributes: `#[HasOne]`, `#[HasMany]`, `#[BelongsTo]`, `#[BelongsToMany]`
- Relationship metadata parsing in `EntityMetadataFactory`
- `RelationshipLoader` service for batch eager loading (no N+1)
- `with()` on Repository and RepositoryQueryBuilder for explicit eager loading
- Nested eager loading via dot notation (`'comments.author'`)
- First-class pivot entities for many-to-many (regular Entity classes)
- `EntityCollection` with typed, chainable methods
- `QuerySpecification` interface and `matching()` on Repository/RepositoryQueryBuilder
- Updated `RepositoryInterface` to return `EntityCollection` from collection methods
- Relationship validation exceptions with helpful messages

### Out of Scope
- Lazy loading / proxy objects
- Identity map / Unit of Work
- Cascade save/delete through relationships
- Inverse relationship auto-sync
- Polymorphic relationships
- Aggregate queries on relationships (withCount, etc.)
- Pagination (separate feature)

## Success Criteria
- [ ] All four relationship types work with explicit eager loading
- [ ] Nested eager loading resolves via batch queries (no N+1)
- [ ] Pivot entities work as standard entities with own repositories
- [ ] EntityCollection provides typed, chainable API
- [ ] QuerySpecification composes multiple specs via matching()
- [ ] RepositoryQueryBuilder supports with() and matching()
- [ ] All tests passing with ≥80% coverage
- [ ] Code follows all project standards (strict types, no magic, loud errors)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | EntityCollection — typed collection class | - | completed |
| 002 | Relationship attributes — HasOne, HasMany, BelongsTo, BelongsToMany | - | completed |
| 003 | RelationshipMetadata — metadata value object for parsed relationships | 002 | completed |
| 004 | EntityMetadataFactory — parse relationship attributes | 002, 003 | completed |
| 005 | QuerySpecification interface and matching() on Repository | 001 | completed |
| 006 | RelationshipLoader — batch eager loading service | 003, 004 | completed |
| 007 | Repository with() — integrate eager loading into Repository | 001, 006 | completed |
| 008 | RepositoryQueryBuilder — add with() and matching() support | 005, 007 | completed |
| 009 | BelongsToMany eager loading — pivot entity resolution | 006 | completed |
| 010 | Nested eager loading — dot-notation relationship chains | 006, 007 | completed |
| 011 | Repository return type migration — findAll/findBy return EntityCollection | 001 | completed |
| 012 | Relationship validation — error messages for misconfigured relationships | 004, 006 | completed |

## Architecture Notes

### Relationship Loading Strategy
Eager loading uses batch queries to avoid N+1:
1. Load primary entities
2. Collect foreign key values from loaded entities
3. Execute single `WHERE column IN (...)` query per relationship
4. Hydrate related entities and assign to parent properties

### EntityCollection Design
- Implements `Countable`, `IteratorAggregate`
- Methods returning entity subsets → `EntityCollection`
- Methods returning grouped/chunked → `EntityCollection<EntityCollection>`
- Terminal methods (scalars) → scalar types
- `map()` returns `array` (exits collection type since transform may change type)

### Relationship Attributes
Placed on entity properties. The property type declares what the relationship holds:
- `?Entity` for BelongsTo/HasOne (nullable, defaults to null)
- `array` for HasMany/BelongsToMany (defaults to [])

Properties are NOT Column attributes — they represent derived data, not stored columns.

### No Lazy Loading by Design
Unloaded relationships stay at their default value (null or []). This is intentional:
- Forces developers to think about data requirements upfront
- Makes N+1 queries impossible
- Makes data flow explicit and debuggable

## Risks & Mitigations
- **Breaking change on return types**: findAll()/findBy() changing from array to EntityCollection — mitigate by making EntityCollection implement array-like interfaces (Countable, IteratorAggregate) and providing toArray()
- **Circular relationships**: A has B has A — mitigate by only loading relationships explicitly requested via with(), not recursively
- **Performance with deep nesting**: `with('a.b.c.d')` could generate many queries — mitigate by keeping batch loading and documenting recommended depth limits
