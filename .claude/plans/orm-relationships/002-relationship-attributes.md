# Task 002: Relationship Attributes — HasOne, HasMany, BelongsTo, BelongsToMany

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the four relationship attribute classes that define how entities relate to each other. These are PHP 8.5 attributes placed on entity properties (not columns) to declare relationship metadata. They follow the same patterns as existing attributes (`#[Table]`, `#[Column]`, `#[Index]`).

## Context
- New files in `packages/database/src/Attributes/`:
  - `HasOne.php`
  - `HasMany.php`
  - `BelongsTo.php`
  - `BelongsToMany.php`
- Test file: `packages/database/tests/Attributes/RelationshipAttributeTest.php`
- Patterns to follow: `packages/database/src/Attributes/Column.php`, `packages/database/src/Attributes/Table.php` — all are readonly classes with `#[Attribute]` targeting properties
- Relationship attributes target properties (`Attribute::TARGET_PROPERTY`)
- They are NOT repeatable (one relationship per property)

## Requirements (Test Descriptions)

### HasOne Attribute
- [ ] `it creates HasOne attribute with entity class and foreign key`
- [ ] `it stores entity class on HasOne attribute`
- [ ] `it stores foreign key on HasOne attribute`
- [ ] `it targets properties only for HasOne`

### HasMany Attribute
- [ ] `it creates HasMany attribute with entity class and foreign key`
- [ ] `it stores entity class on HasMany attribute`
- [ ] `it stores foreign key on HasMany attribute`
- [ ] `it targets properties only for HasMany`

### BelongsTo Attribute
- [ ] `it creates BelongsTo attribute with entity class and foreign key`
- [ ] `it stores entity class on BelongsTo attribute`
- [ ] `it stores foreign key on BelongsTo attribute`
- [ ] `it targets properties only for BelongsTo`

### BelongsToMany Attribute
- [ ] `it creates BelongsToMany attribute with entity class pivot class and keys`
- [ ] `it stores entity class on BelongsToMany attribute`
- [ ] `it stores pivot class on BelongsToMany attribute`
- [ ] `it stores foreign key on BelongsToMany attribute`
- [ ] `it stores related key on BelongsToMany attribute`
- [ ] `it targets properties only for BelongsToMany`

## Acceptance Criteria
- All requirements have passing tests
- All attribute classes are readonly
- All use `#[Attribute(Attribute::TARGET_PROPERTY)]`
- Constructor uses property promotion
- Strict types declared
- Entity class parameters typed as `class-string<Entity>`
- Pivot class parameter on BelongsToMany typed as `class-string<Entity>`
- `foreignKey` semantics differ by relationship type (document in PHPDoc on each attribute):
  - `HasOne::foreignKey` = property name on the RELATED entity's class pointing back to this entity (e.g., `'user_id'` on Profile)
  - `HasMany::foreignKey` = property name on the RELATED entity's class pointing back to this entity (e.g., `'post_id'` on Comment)
  - `BelongsTo::foreignKey` = property name on THIS entity's class pointing to the related entity (e.g., `'author_id'` on Post)
  - `BelongsToMany::foreignKey` = property name on the PIVOT entity pointing to this entity
  - `BelongsToMany::relatedKey` = property name on the PIVOT entity pointing to the related entity

## Implementation Notes
(Left blank - filled in by programmer during implementation)
