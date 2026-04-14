# Task 006: Update Documentation

**Status**: completed
**Depends on**: 001, 005
**Retry count**: 0

## Description
Update all documentation to reflect the new auto-conversion convention: camelCase property names are automatically converted to snake_case column names. Remove redundant explicit `name:` parameters from doc examples and add explanation of the convention.

## Context
- `docs/src/content/docs/guides/database.md` — Entity examples, mention auto-conversion
- `docs/src/content/docs/packages/database.md` — Column attribute docs, document convention, note `name:` override
- `docs/src/content/docs/packages/database-mysql.md` — Update examples if any use explicit names
- `docs/src/content/docs/packages/database-pgsql.md` — Update examples if any use explicit names
- `docs/src/content/docs/tutorials/build-an-admin-panel.md` — Multiple entity examples with explicit `#[Column('...')]`
- `docs/src/content/docs/tutorials/build-a-chat.md` — Entity with `#[Column('created_at')]`
- `docs/src/content/docs/tutorials/custom-module.md` — Entity with `#[Column('user_id')]`, `#[Column('viewed_at')]`
- `docs/src/content/docs/tutorials/build-a-blog.md` — Entity with `#[Column('published_at')]`, `#[Column('created_at')]`
- `docs/src/content/docs/tutorials/build-a-rest-api.md` — Entity with `#[Column('author_email')]`, `#[Column('created_at')]`, `#[Column('updated_at')]`
- `packages/database/README.md` — Quick example entity

**Key changes per file:**
- Remove redundant `#[Column('snake_name')]` where property auto-converts correctly
- Keep explicit names that DON'T match auto-conversion (e.g., `#[Column('email_address')]` on `$email`, `#[Column('author_email')]` on `$authorEmail`)
- Add a note about the auto-conversion convention where Column attributes are documented
- Mention that `name:` override is available for custom mappings

**Example of what to check:** `#[Column('author_email')]` on property `$authorEmail` — `authorEmail` auto-converts to `author_email`, so this IS redundant. But `#[Column(name: 'author_id', references: 'users.id')]` on `$authorId` — name is redundant, keep `references`.

## Requirements (Test Descriptions)
- [ ] `it removes redundant explicit Column names from database guide examples`
- [ ] `it removes redundant explicit Column names from tutorial examples`
- [ ] `it documents auto-conversion convention in database package docs`
- [ ] `it preserves explicit name overrides that differ from auto-conversion`
- [ ] `it updates README quick example if it has redundant names`

## Acceptance Criteria
- All doc entity examples use bare `#[Column]` where auto-conversion suffices
- Database package docs explain: property names auto-convert to snake_case, `name:` overrides
- Tutorial examples updated consistently
- No doc examples show redundant explicit names
- Build docs site to verify no broken formatting (if possible)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
