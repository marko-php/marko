# Plan: Harmonize Database Driver Packages

## Created
2026-01-21

## Status
in_progress

## Objective
Make database-mysql and database-pgsql packages coherent by standardizing naming conventions, code style, and patterns. These official driver packages should read as if written by the same person.

## Scope

### In Scope
- Standardize method naming conventions across both packages
- Harmonize PHPDoc style
- Fix readonly modifier consistency
- Add proper namespaces to PostgreSQL tests
- Standardize count alias naming
- Unify connection patterns (ensureConnected)
- Align method visibility
- Standardize implementation patterns (first(), orderBy())
- Align DSN method naming and visibility
- Harmonize testing approach in Connection tests

### Out of Scope
- Changing database-specific behavior (SQL syntax differences)
- Adding new functionality
- Changing public API signatures
- Performance optimizations

## Success Criteria
- [ ] All tests passing after changes
- [ ] Both packages use identical naming conventions
- [ ] Both packages use identical code style patterns
- [ ] Code follows project standards
- [ ] No functional regressions

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Harmonize QueryBuilder method naming | - | completed |
| 002 | Harmonize Generator method naming | - | completed |
| 003 | Harmonize Connection classes | - | completed |
| 004 | Fix PgSqlIntrospector readonly modifier | - | completed |
| 005 | Harmonize PHPDoc style in MySQL package | - | completed |
| 006 | Add namespaces to PostgreSQL tests | - | completed |
| 007 | Harmonize Connection test approach | 003, 006 | completed |
| 008 | Run tests and verify all changes | 001, 002, 003, 004, 005, 006, 007 | pending |

## Architecture Notes

### Standard to Adopt
After analysis, we adopt **PostgreSQL conventions** as the standard (where applicable) with some MySQL additions:

1. **Method Naming**: Use `*Clause` suffix (PostgreSQL style) - more descriptive
2. **PHPDoc Style**: Multi-line format (PostgreSQL style) - cleaner
3. **Connection Pattern**: `ensureConnected()` abstraction (PostgreSQL style) - cleaner
4. **Visibility**: `quoteIdentifier()` as `protected` (PostgreSQL style) - internal detail
5. **Count Alias**: Use `count` (PostgreSQL style) - simpler
6. **first() Implementation**: Use `$this->limit(1)` (PostgreSQL style) - chainable
7. **orderBy() Validation**: Add validation (MySQL style) - safer
8. **DSN Methods**: Keep driver-specific approach but align visibility
9. **Testing**: MySQL's anonymous class approach (more explicit, avoids reflection)

### Files Affected

**MySQL Package (to change):**
- `src/Query/MySqlQueryBuilder.php` - method naming, PHPDoc, implementation patterns
- `src/Sql/MySqlGenerator.php` - method naming, PHPDoc
- `src/Connection/MySqlConnection.php` - add ensureConnected pattern

**PostgreSQL Package (to change):**
- `src/Query/PgSqlQueryBuilder.php` - PHPDoc consistency, orderBy validation
- `src/Introspection/PgSqlIntrospector.php` - add readonly modifier
- `src/Connection/PgSqlConnection.php` - add testable createPdo pattern
- All test files in `tests/` - add proper namespaces

## Risks & Mitigations
- **Method renaming breaks tests**: Run tests after each task to catch issues early
- **PHPDoc changes affect IDE**: Pure formatting, no functional impact
- **Test namespace changes**: Update use statements along with namespaces
