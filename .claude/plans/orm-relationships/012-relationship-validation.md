# Task 012: Relationship Validation — Error Messages for Misconfigured Relationships

**Status**: pending
**Depends on**: 004, 006
**Retry count**: 0

## Description
Add validation and helpful error messages for common relationship misconfigurations. This follows Marko's "loud errors" principle — invalid relationships should fail fast with clear messages explaining what's wrong, what caused it, and how to fix it. Validation happens at metadata parse time and at load time.

## Context
- Modify: `packages/database/src/Entity/EntityMetadataFactory.php` (parse-time validation)
- Modify: `packages/database/src/Entity/RelationshipLoader.php` (load-time validation)
- Modify: `packages/database/src/Exceptions/EntityException.php` (new factory methods)
- Modify: `packages/database/src/Exceptions/RepositoryException.php` (new factory methods)
- Test file: `packages/database/tests/Entity/RelationshipValidationTest.php`
- Patterns to follow: existing exception factory methods in `EntityException` and `RepositoryException` — all use static factory methods with message, context, suggestion parameters

## Requirements (Test Descriptions)

### Parse-Time Validation (EntityMetadataFactory)
- [ ] `it throws when relationship property also has Column attribute`
- [ ] `it throws when BelongsToMany is missing pivot class`
- [ ] `it throws when relationship entity class does not extend Entity`
- [ ] `it throws when singular relationship property is not nullable entity type` (HasOne/BelongsTo require `?EntitySubclass` type -- check via `ReflectionNamedType::allowsNull()` and verify the type is a subclass of Entity)
- [ ] `it throws when collection relationship property is not array type` (HasMany/BelongsToMany require `array` type)

### Load-Time Validation (RelationshipLoader)
- [ ] `it throws when loading undefined relationship name`
- [ ] `it throws when RelationshipLoader has no query builder factory`

### Eager Validation (with() call time)
- [ ] `it throws when with is called with a relationship name that does not exist on the entity`
- [ ] `it includes entity class and invalid relationship name in error message`

### Error Message Quality
- [ ] `it includes entity class name in relationship error context`
- [ ] `it includes property name in relationship error context`
- [ ] `it includes suggestion for fixing Column and relationship conflict`
- [ ] `it includes suggestion for fixing type mismatch`

## Acceptance Criteria
- All requirements have passing tests
- All exceptions use static factory methods with message, context, suggestion
- Parse-time errors caught during EntityMetadataFactory::parse()
- Load-time errors caught during RelationshipLoader::load()
- Error messages are specific and actionable
- Strict types, @throws tags on all methods that throw

## Implementation Notes
(Left blank - filled in by programmer during implementation)
