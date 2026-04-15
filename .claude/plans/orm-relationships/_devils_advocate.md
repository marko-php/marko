# Devil's Advocate Review: orm-relationships

## Critical (Must fix before building)

### C1. EntityMetadata is `readonly` -- adding `relationships` property breaks construction (Task 004)

`EntityMetadata` is a `readonly class`. Its constructor has named parameters that are used directly throughout the test suite (EntityMetadataTest, EntityHydratorTest -- at least 9 call sites constructing `new EntityMetadata(...)`). Task 004 says to add a `relationships` property, but since the constructor uses named params with defaults, this is backward-compatible IF the new parameter has a default value.

**The risk:** Task 004 does say "EntityMetadata backward-compatible (relationships defaults to empty array)" which is correct. However, the task does NOT mention that the worker must also update `EntityMetadataFactory::parse()` to pass the new `relationships` parameter when constructing `EntityMetadata`. The factory currently constructs `EntityMetadata` on line 139-146 -- the worker needs to add the relationships array there.

**Fix:** Add explicit note to task 004 that `EntityMetadataFactory::parse()` must be updated to pass `relationships` to the `EntityMetadata` constructor.

### C2. RepositoryQueryBuilder is `readonly` -- cannot add mutable state for `with()`/`matching()` (Task 008)

`RepositoryQueryBuilder` is declared as `readonly class`. Task 008 acknowledges this ("may need to drop readonly") but does not make it a firm requirement. A worker might try to add `with()` state to a readonly class and hit a wall.

The `readonly` keyword prevents adding any mutable properties. The worker MUST either:
- Remove `readonly` from the class and use a clone-based immutable pattern
- Or use a wrapper/builder that holds the mutable state

**Fix:** Make dropping `readonly` an explicit requirement in task 008, not a "may need to."

### C3. RelationshipLoader needs QueryBuilderFactoryInterface, not ConnectionInterface (Task 006)

Task 006 lists `ConnectionInterface` as a dependency in the context section, but the loader needs to execute `WHERE column IN (...)` queries. Looking at how the codebase works:
- `ConnectionInterface` has `query(string $sql, array $bindings)` for raw SQL
- `QueryBuilderFactoryInterface` creates `QueryBuilderInterface` instances with `whereIn()` method

The task also lists `QueryBuilderFactoryInterface` as a dependency. However, the acceptance criteria says "Constructor injection with EntityMetadataFactory, EntityHydrator, QueryBuilderFactoryInterface" -- no `ConnectionInterface`. The context section contradicts this by listing both. The worker needs clarity on which approach to use.

**Fix:** Remove `ConnectionInterface` from task 006 context dependencies. The loader should use `QueryBuilderFactoryInterface` to create query builders, which is consistent with how `Repository` works.

### C4. Task 007 `with()` cloning pattern conflicts with Repository constructor validation (Task 007)

Task 007 says `with()` returns a new Repository instance (immutable fluent pattern). But `Repository::__construct()` calls `$this->validateEntityClass()` and `$this->metadataFactory->parse(static::ENTITY_CLASS)`. If `with()` uses `clone()` this is fine, but if it tries to construct a new instance, it will re-run validation and re-parse metadata unnecessarily.

Additionally, Repository is `abstract` -- you cannot `new static(...)` without knowing the concrete class. The plan should specify using `clone` (PHP 8.5's `clone() with` or traditional clone + property setting).

**Fix:** Specify in task 007 that `with()` must use `clone` pattern (not new construction), and that the cloned instance carries over the already-parsed `$this->metadata`, relationship loader, etc. Only the pending relationship names differ.

### C5. Repository has no `$relationshipLoader` property -- task 007 must also handle DI wiring (Task 007)

The plan says "Repository constructor gains optional `?RelationshipLoader $relationshipLoader = null`" but never addresses how this gets wired via DI. Currently, concrete repositories are instantiated by the DI container which autowires constructor params. `RelationshipLoader` is a new class -- it needs to be registered/bindable.

More critically, when `with()` clones the repository, the clone needs the RelationshipLoader. But if the original repository was constructed without one (because the app doesn't use relationships), calling `with()` should throw. This IS covered in the test requirements but needs to be explicit in the implementation notes.

**Fix:** Add note to task 007 that RelationshipLoader will be autowired by the container (it's a concrete class, so autowiring works). The `with()` method must check for null RelationshipLoader and throw `RepositoryException` before cloning.

## Important (Should fix before building)

### I1. Task 006 needs EntityMetadataFactory to resolve related entity metadata (Task 006)

When loading a BelongsTo relationship, the RelationshipLoader needs to know the TABLE NAME of the related entity (to query `SELECT * FROM related_table WHERE id IN (...)`). This means it needs to call `EntityMetadataFactory::parse()` on the related entity class to get its table name and hydration metadata.

Task 006 lists `EntityMetadataFactory` as a dependency but doesn't explicitly call out this flow. The worker might not realize they need to parse the RELATED entity's metadata (not just the parent's).

**Fix:** Add explicit requirement to task 006 noting that the loader must call `metadataFactory->parse()` on the related entity class (from `RelationshipMetadata::$relatedEntityClass`) to discover its table name and column mappings for hydration.

### I2. BelongsTo loading needs the PARENT's foreign key column, not just property name (Task 006)

For `#[BelongsTo(entity: Author::class, foreignKey: 'author_id')]`, the `foreignKey` is a column on the PARENT entity's table. The loader needs to:
1. Read the parent entity's `author_id` property value (the FK value)
2. Query the related `authors` table: `SELECT * FROM authors WHERE id IN (...)`

But `foreignKey: 'author_id'` -- is this a property name or column name? The attribute stores it as a string, and it could be either. The plan needs to clarify whether `foreignKey` in the attribute refers to the property name (which gets mapped to a column via PropertyMetadata) or the actual database column name.

Looking at how `#[Column('email_address')]` works (where property `email` maps to column `email_address`), the convention suggests `foreignKey` should be a property name. But then the loader needs to look up `PropertyMetadata` for that property to get the actual column name for the query.

**Fix:** Add clarification to tasks 002 and 006 that `foreignKey` in relationship attributes refers to the PROPERTY name on the entity (not the database column name), and that the loader must resolve it to a column name via `PropertyMetadata`.

### I3. HasOne/HasMany foreignKey semantics differ from BelongsTo (Tasks 002, 006)

For `#[HasMany(entity: Comment::class, foreignKey: 'post_id')]`, the `foreignKey` is a column on the RELATED entity's table (comments), not the parent's. But for `#[BelongsTo(entity: Post::class, foreignKey: 'post_id')]`, the `foreignKey` is on the PARENT entity's table.

This asymmetry is standard ORM behavior but is not documented anywhere in the plan. Workers building task 002 (attributes) and task 006 (loader) independently might interpret `foreignKey` the same way for all relationship types.

**Fix:** Add explicit documentation in task 002 clarifying:
- `HasOne::foreignKey` = column on the RELATED entity's table pointing back to this entity
- `HasMany::foreignKey` = column on the RELATED entity's table pointing back to this entity
- `BelongsTo::foreignKey` = column on THIS entity's table pointing to the related entity
- `BelongsToMany::foreignKey` = column on the PIVOT table pointing to this entity
- `BelongsToMany::relatedKey` = column on the PIVOT table pointing to the related entity

### I4. How does RelationshipLoader know the parent entity's primary key column? (Task 006)

For HasOne/HasMany loading, the loader collects parent entity PK values to build `WHERE foreign_key IN (parent_pk_values)`. But it needs to know the parent entity's primary key property name to extract those values via reflection. The plan doesn't mention this -- the loader needs the PARENT's `EntityMetadata` (which it can get from the factory), but this needs to be explicit.

**Fix:** Add to task 006 requirements that the loader receives/resolves the parent `EntityMetadata` to determine the primary key property for extracting FK match values.

### I5. Task 011 depends on task 007 but could be simplified (Task 011)

Task 011 (return type migration) depends on 007 (Repository with). But the actual change is simple: wrap `array_map(...)` results in `new EntityCollection(...)` in `findAll()` and `findBy()`. This doesn't logically depend on relationship loading at all -- it depends only on task 001 (EntityCollection class existing).

The dependency on 007 seems to be because 007 also modifies Repository. But parallel workers modifying the same file is a merge concern, not a logical dependency. If these run sequentially per the dependency chain, task 007 already modifies Repository, and then 011 modifies it again.

**Fix:** Change task 011 dependencies from "001, 007" to just "001". Add a note that this task modifies `Repository.php` which is also modified by tasks 005, 007 -- workers should be aware of merge conflicts. Moving 011 earlier in the chain lets it complete independently.

### I6. Task 005 `matching()` on Repository uses `findAll`/`findBy` which return `array` until task 011 (Task 005)

Task 005 says `matching()` returns `EntityCollection`. But it depends only on task 001 (EntityCollection). At the time task 005 runs, `Repository::findAll()` still returns `array`. So `matching()` must independently wrap results in `EntityCollection`, duplicating what task 011 eventually does for findAll/findBy.

This is actually fine -- the worker just needs to know they must wrap the query builder results themselves. But the task should make this explicit.

**Fix:** Add note to task 005 that `matching()` must create its own `EntityCollection` from query results (using `getEntities()` pattern), not delegate to `findAll()`/`findBy()`.

### I7. `pluck()` method on EntityCollection assumes public property access (Task 001)

Task 001 includes `pluck()` which extracts a property value from all entities. Entity properties ARE public (as seen in test fixtures), so this works. But the plan should specify whether `pluck()` uses direct property access or reflection. Direct property access is simpler and consistent with entities having public properties.

The edge case: what if someone plucks a relationship property that hasn't been loaded? They'll get `null` or `[]` (the defaults). This is correct behavior but worth noting.

**Fix:** Add note to task 001 that `pluck()` uses direct property access (entities have public properties per convention), and that unloaded relationship properties return their default values.

### I8. Task 012 validation "throws when relationship property type does not match relationship kind" needs specifics

Task 012 says to validate type matches, but doesn't specify what the valid types are. Based on the plan:
- `HasOne` / `BelongsTo`: property should be `?EntitySubclass` (nullable single entity)
- `HasMany` / `BelongsToMany`: property should be `array`

But the worker needs to know how to validate this via reflection. The property type for `?Author` would be `ReflectionNamedType` with name `Author` and `allowsNull() === true`. For `array`, it's straightforward.

**Fix:** Add specific validation rules to task 012: singular relationships require nullable type that is a subclass of Entity; collection relationships require `array` type.

## Minor (Nice to address)

### M1. EntityCollection generic type `<T of Entity>` cannot be enforced at runtime in PHP

Task 001 describes `EntityCollection<T of Entity>` but PHP doesn't support runtime generics. The generic is purely a PHPDoc/static-analysis concern. The collection will accept any Entity at runtime. This is fine but worth acknowledging -- the worker shouldn't try to add runtime type checking in the constructor.

### M2. `sortBy()` on EntityCollection needs property access strategy

Task 001 includes sorting by a property name (string). Like `pluck()`, this uses dynamic property access. The worker should use a callback-based API (`sortBy(callable)`) or property-name-based API. The test descriptions say "sorts entities by a property" suggesting string-based, which means dynamic property access via `$entity->$property`.

### M3. Test file placement inconsistency -- FIXED

All tasks originally specified `tests/Unit/` paths but existing database package tests use `tests/Attributes/`, `tests/Entity/`, `tests/Repository/`, `tests/Query/` directly (no `Unit/` prefix). All task files have been updated to match existing convention.

### M4. `EntityCollection::each()` returns void but could return self for chaining

The plan says `each` applies a callback "without returning." Returning `$this` (or a new instance) would allow chaining, which is more consistent with the collection's fluent API. This is a design preference.

## Questions for the Team

### Q1. Should `findOneBy()` also return entities with relationships loaded when called on a `with()` repository?

Task 007 lists `findOneBy` in requirements but `findOneBy` currently delegates to `findBy` and takes the first result. When `with()` is active, should `findOneBy` load relationships? The answer is probably yes (and the task implies it), but it means `findBy` returning an `EntityCollection` (task 011) needs to happen before or simultaneously with task 007's relationship loading.

### Q2. Should BelongsToMany `foreignKey` and `relatedKey` refer to property names on the pivot entity or column names?

If they're property names, the loader needs the pivot entity's `PropertyMetadata` to resolve to column names. If they're column names, they can be used directly in queries. The pivot entity IS a full Entity with `#[Column]` attributes, so either approach works.

### Q3. What happens when `with('nonExistent')` is called?

Task 012 covers "throws when loading undefined relationship name" but should this validation happen eagerly (at `with()` call time) or lazily (at query execution time)? Eager validation requires metadata parsing at `with()` time. Lazy validation defers it to `RelationshipLoader::load()`.
