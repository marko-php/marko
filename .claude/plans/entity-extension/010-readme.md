# Task 010: README

**Status**: completed
**Depends on**: [007, 008, 009]
**Retry count**: 0

## Description
Update the `marko/database` package README to document the entity extension system. The README should cover how to declare an extension, what the framework does automatically, how to access extensions on fetched entities, the save-time null/default/error policy, and column conflict behaviour.

## Context
- Related files: `packages/database/README.md`
- Follow the existing README structure and tone
- Document the complete developer workflow:
  1. Create an `EntityExtension` subclass in `src/EntityExtension/`
  2. Annotate with `#[ExtensionOf(TargetEntity::class)]`
  3. Declare `#[Column]` properties
  4. Run migrations to add the new columns
  5. Access via `$entity->extension(MyExtension::class)`
  6. Set before saving: `$entity->setExtension(new MyExtension(...))`
- Include a note about the null/default/error policy on save
- Include a note about the column conflict detection (column name AND property name collisions)
- Include a note that UPDATEs always rewrite ALL extension columns (no dirty tracking on extensions)
- Include a note that an extension is skipped during hydration when none of its columns are present in the row (so freshly added extensions before migration won't crash hydration)
- Include a note that extensions cannot declare primary keys, relationships, indexes, or `#[Table]`
- Do NOT document out-of-scope items (separate-table extensions, dirty tracking on extensions)

## Requirements (Test Descriptions)
- [x] `it has a README that documents entity extensions`

A structural test that asserts `README.md` exists and contains the string `EntityExtension` is sufficient.

## Acceptance Criteria
- README exists and covers the entity extension feature
- Code follows project standards
