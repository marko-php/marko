# Task 005: Schema Value Objects

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the internal schema value objects: Table, Column, Index, and ForeignKey. These are immutable objects that represent database structure. They are NOT user-facing (users define schema via Entity attributes), but are used internally by the diff engine and SQL generators.

## Context
- Related files: packages/database/src/Schema/
- Patterns to follow: Immutable value objects, readonly classes
- These objects are built FROM entity metadata, not written directly by users

## Requirements (Test Descriptions)
- [x] `it creates readonly Table class with name and columns`
- [x] `it creates readonly Column class with name, type, and constraints`
- [x] `it supports column properties: nullable, default, unique, primaryKey, autoIncrement`
- [x] `it supports column foreign key reference with onDelete/onUpdate`
- [x] `it creates readonly Index class with name, columns, and type (btree, unique, fulltext)`
- [x] `it creates readonly ForeignKey class with columns, references, and actions`
- [x] `it provides Table::withColumn() for immutable building`
- [x] `it provides Table::withIndex() for immutable building`
- [x] `it provides Column::withConstraint() style methods`
- [x] `it implements equals() method for diff comparison`

## Acceptance Criteria
- All requirements have passing tests
- All classes are readonly/immutable
- Objects can be compared for equality (for diff)
- Clear separation from user-facing Entity attributes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
