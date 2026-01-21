# Task 004: Update Driver Module Bindings

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Update the module.php files in both database-mysql and database-pgsql to bind ConnectionInterface using the factory. The binding should get the factory from the container and call create().

## Context
- Related files:
  - `packages/database-mysql/module.php` (modify)
  - `packages/database-pgsql/module.php` (modify)
  - `packages/database/module.php` (may need to create for DatabaseConfig binding)
- Patterns to follow: Existing module.php files in the codebase

Note: The container supports closure bindings. The closure receives the container and should return the created instance.

Example pattern:
```php
'bindings' => [
    ConnectionInterface::class => function (ContainerInterface $container): ConnectionInterface {
        return $container->get(MySqlConnectionFactory::class)->create();
    },
],
```

## Requirements (Test Descriptions)
- [x] `it binds ConnectionInterface to factory-created MySqlConnection in mysql driver`
- [x] `it binds ConnectionInterface to factory-created PgSqlConnection in pgsql driver`
- [x] `it resolves ConnectionInterface to working connection when config exists`
- [x] `it throws ConfigurationException when config file missing`

## Acceptance Criteria
- All requirements have passing tests
- Both driver module.php files follow same pattern
- Running `marko db:migrate` works with just config/database.php
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
