# Plan: Blog Database Integration

## Created
2026-01-21

## Status
in_progress

## Objective
Update the marko/blog package to use the database layer for Post entity and repository, enabling database-driven blog functionality that works with any database driver (MySQL, PostgreSQL, or future drivers).

## Scope

### In Scope
- Post entity with `#[Table]` and `#[Column]` attributes
- PostRepository extending the abstract Repository base class
- PostController updated to use PostRepository for data access
- Simple echo/var_dump output for routes (no views)
- Blog list (index) and blog detail (show by slug) endpoints
- marko/database dependency in composer.json
- Full test coverage with Pest 4

### Out of Scope
- Views or templates (marko/view not yet available)
- Admin interface or CRUD operations beyond read
- Pagination, filtering, or search
- Database migrations (entities define schema, migrations are a separate concern)
- demo/app customization (tests verify functionality)

## Success Criteria
- [ ] Post entity defines table schema via attributes
- [ ] PostRepository provides data access methods
- [ ] PostController uses repository to fetch posts from database
- [ ] Blog depends only on marko/database (not specific drivers)
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Update composer.json dependencies | - | completed |
| 002 | Create Post entity with attributes | 001 | pending |
| 003 | Create PostRepository | 002 | pending |
| 004 | Update PostController to use repository | 003 | pending |

## Architecture Notes

### Database Abstraction Pattern
The blog package depends ONLY on `marko/database` which provides:
- `ConnectionInterface` - abstract database connection
- `Repository` abstract class - base for all repositories
- `Entity` base class - marker for entity classes
- `#[Table]`, `#[Column]` attributes - schema definition

The end user installs whichever driver they need:
```bash
composer require marko/blog marko/database-mysql
# OR
composer require marko/blog marko/database-pgsql
```

The blog code never knows or cares which driver is installed.

### Entity Schema Definition
Entities are the source of truth for database schema. Type inference:
- `int` → INT
- `string` → VARCHAR
- `?type` → NULLABLE
- `DateTimeImmutable` → TIMESTAMP

### Repository Pattern
- Concrete repositories define `ENTITY_CLASS` constant
- Base class provides `find()`, `findAll()`, `findBy()`, `findOneBy()`, `save()`, `delete()`
- Constructor receives `ConnectionInterface`, `EntityMetadataFactory`, `EntityHydrator`

## Risks & Mitigations
- **Risk**: Tests need mock database connections
  - **Mitigation**: Create mock ConnectionInterface implementations in tests (pattern established in database package tests)
