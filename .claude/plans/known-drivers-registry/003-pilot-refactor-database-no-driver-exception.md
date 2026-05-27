# Task 003: Pilot — refactor database NoDriverException to read known-drivers.php

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Refactor `Marko\Database\Exceptions\NoDriverException` to read its driver list from `known-drivers.php` instead of a hardcoded `DRIVER_PACKAGES` const. Include descriptions and derived docs URLs in the formatted suggestion text. Establishes the refactor pattern that all 17 other `NoDriverException` classes will follow.

## Context
- Files to modify:
  - `packages/database/src/Exceptions/NoDriverException.php` (remove `DRIVER_PACKAGES` const; load known-drivers.php at exception-construction time)
  - `packages/database/tests/NoDriverExceptionTest.php` — has an existing test asserting `DRIVER_PACKAGES` const exists (lines 9-16 at plan creation). Remove that test or replace it with a "no longer exposes DRIVER_PACKAGES" assertion. Update the suggestion-text assertions to match the new format.
- Reference: previous implementation in `packages/view/src/Exceptions/NoDriverException.php` (which still has the hardcoded const — this task supersedes that pattern for database; view gets refactored in task 016)

**New suggestion text format:**
```
Install one of these drivers:
- marko/database-pgsql: PostgreSQL driver (recommended for new projects — strong JSON, FTS, pgvector support)
  Install: composer require marko/database-pgsql
  Docs: https://marko.build/docs/packages/database-pgsql/
- marko/database-mysql: MySQL/MariaDB driver
  Install: composer require marko/database-mysql
  Docs: https://marko.build/docs/packages/database-mysql/
```

**Implementation shape:**
```php
class NoDriverException extends MarkoException
{
    public static function noDriverInstalled(): self
    {
        $drivers = require __DIR__ . '/../../known-drivers.php';
        $packageList = self::formatDriverList($drivers);

        return new self(
            message: 'No database driver installed.',
            context: 'Attempted to resolve a database interface but no implementation is bound.',
            suggestion: "Install one of these drivers:\n{$packageList}",
        );
    }

    /**
     * @param array<string, string> $drivers
     */
    private static function formatDriverList(array $drivers): string
    {
        $lines = [];
        foreach ($drivers as $package => $description) {
            $docsUrl = self::docsUrl($package);
            $lines[] = "- {$package}: {$description}";
            $lines[] = "  Install: composer require {$package}";
            $lines[] = "  Docs: {$docsUrl}";
        }
        return implode("\n", $lines);
    }

    private static function docsUrl(string $package): string
    {
        $basename = substr($package, strlen('marko/'));
        return "https://marko.build/docs/packages/{$basename}/";
    }
}
```

## Requirements (Test Descriptions)
- [ ] `it loads the driver list from known-drivers.php`
- [ ] `it includes the description for each driver in the suggestion`
- [ ] `it includes a composer require command for each driver`
- [ ] `it includes a derived docs URL for each driver`
- [ ] `it derives docs URLs from the package basename (marko slash prefix stripped)`
- [ ] `it lists pgsql first in the suggestion (matching known-drivers.php order)`
- [ ] `it no longer exposes a DRIVER_PACKAGES const`

## Acceptance Criteria
- `NoDriverException::DRIVER_PACKAGES` const is removed
- Exception reads from `known-drivers.php` via `require __DIR__ . '/../../known-drivers.php'`
- Suggestion text includes description + composer require command + docs URL for each driver
- All existing tests pass (with updated assertions)
- New tests added for the docs URL derivation behavior
- Code follows code standards
