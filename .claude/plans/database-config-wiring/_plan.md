# Plan: Database Config Wiring

## Created
2026-01-21

## Status
in_progress

## Objective
Fix the database driver packages so that ConnectionInterface can be autowired without users needing to manually create factory bindings. The drivers should automatically read config/database.php and create the connection.

## Scope

### In Scope
- Update DatabaseConfig to be injectable (default base path)
- Create ConnectionFactory in marko/database-mysql
- Create ConnectionFactory in marko/database-pgsql
- Update module.php bindings in both driver packages
- Maintain sibling module standards between drivers

### Out of Scope
- Changes to the DI container itself
- New configuration file formats
- Connection pooling or advanced connection management

## Success Criteria
- [ ] DatabaseConfig can be autowired by the container
- [ ] MySqlConnectionFactory creates connections using config
- [ ] PgSqlConnectionFactory creates connections using config
- [ ] Running `marko db:migrate` works with just config/database.php (no manual bindings)
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Make DatabaseConfig injectable | - | completed |
| 002 | Create MySqlConnectionFactory | 001 | pending |
| 003 | Create PgSqlConnectionFactory | 001 | pending |
| 004 | Update driver module.php bindings | 002, 003 | pending |

## Architecture Notes

### Config Resolution
DatabaseConfig will use `getcwd()` as the default base path. This works because:
- CLI commands run from project root
- Web requests have document root set appropriately
- Tests can override by providing explicit path

### Factory Pattern
Each driver creates a factory class:
```
MySqlConnectionFactory
  - Receives: DatabaseConfig (via DI)
  - Creates: MySqlConnection with config values
```

The factory is a simple class with a `create()` method, allowing the container to instantiate it and call the method.

### Module Bindings
Driver module.php files will bind ConnectionInterface to a closure that:
1. Gets the factory from the container
2. Calls create() to get the connection

```php
'bindings' => [
    ConnectionInterface::class => fn(Container $c) => $c->get(MySqlConnectionFactory::class)->create(),
],
```

## Risks & Mitigations
- **Risk**: getcwd() might not be project root in all contexts
  - **Mitigation**: DatabaseConfig constructor still accepts explicit path for override
- **Risk**: Breaking change if anyone relies on current behavior
  - **Mitigation**: This is a fix, not a breaking change - current behavior doesn't work anyway
