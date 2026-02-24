# Task 001: Package Scaffolding and Base Infrastructure

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/testing` package with its directory structure, composer.json, module.php, and the base `AssertionFailedException` that all fake assertion methods will throw on failure.

## Context
- Related files: `packages/core/composer.json` (reference for composer.json format), `packages/core/module.php` (reference for module.php format)
- Patterns to follow: Existing package structure in `packages/`
- Namespace: `Marko\Testing`
- The package must NOT have `"version"` in composer.json (let Composer infer from branch)
- All files need `declare(strict_types=1)`
- Exception must follow Marko's loud error pattern (message, context, suggestion) extending `MarkoException`

## Requirements (Test Descriptions)
- [ ] `it has a valid composer.json with correct name, namespace, and dependencies`
- [ ] `it has PSR-4 autoloading configured for Marko\Testing namespace`
- [ ] `it requires interface packages as dependencies (core, config, mail, queue, session, log, authentication)`
- [ ] `it has a module.php with correct module configuration`
- [ ] `it creates AssertionFailedException extending MarkoException with message, context, and suggestion`
- [ ] `it creates AssertionFailedException with static factory methods for common assertion failures`

## Acceptance Criteria
- All requirements have passing tests
- Package structure matches conventions (src/, tests/, composer.json, module.php)
- AssertionFailedException provides helpful messages following loud error pattern
- Code follows all code standards (strict types, multiline params, no final class on exception)

## Implementation Notes
### composer.json structure
```json
{
    "name": "marko/testing",
    "description": "Testing utilities for the Marko framework - fakes, assertions, and Pest integration",
    "type": "library",
    "require": {
        "php": ">=8.5",
        "marko/core": "^0.1",
        "marko/config": "^0.1",
        "marko/mail": "^0.1",
        "marko/queue": "^0.1",
        "marko/session": "^0.1",
        "marko/log": "^0.1",
        "marko/authentication": "^0.1"
    },
    "require-dev": {
        "pestphp/pest": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "Marko\\Testing\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Marko\\Testing\\Tests\\": "tests/"
        }
    }
}
```

### AssertionFailedException factory methods
- `::expectedDispatched(string $eventClass)` - "Expected event X to be dispatched, but it was not"
- `::unexpectedDispatched(string $eventClass)` - "Expected event X NOT to be dispatched, but it was"
- `::expectedCount(string $type, int $expected, int $actual)` - "Expected N items, got M"
- `::expectedContains(string $type, string $needle)` - "Expected collection to contain X"
- `::unexpectedContains(string $type, string $needle)` - "Expected collection NOT to contain X"

Keep factory methods generic enough to be reused across all fakes (events, mail, queue, etc.).
