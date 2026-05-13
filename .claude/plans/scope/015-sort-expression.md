# Task 015: `ScopeSortExpression` + `ScopeSortRendererInterface`

**Status**: complete
**Depends on**: 002, 008
**Retry count**: 0

## Description
Driver-agnostic representation of a scope-aware sort: which property, the column it falls back to, the axes-and-paths in priority+walk order, and the direction. The `ScopeSortRendererInterface` is implemented by drivers to emit DB-specific SQL.

## Context
- Related files: `packages/scope/src/Query/ScopeSortExpression.php`, `packages/scope/src/Query/ScopeSortRendererInterface.php` (new)
- Patterns to follow: Value object + interface. Renderers in driver packages implement the interface.

## Requirements (Test Descriptions)
- [x] `it creates a ScopeSortExpression with property, column, axis walk paths in order, and direction`
- [x] `it is a readonly value object`
- [x] `it validates direction is asc or desc`
- [x] `it defines ScopeSortRendererInterface with render(ScopeSortExpression) returning a SQL fragment`
- [x] `it documents that renderers must produce a COALESCE expression matching the walker order`

## Acceptance Criteria
- `ScopeSortExpression` is `readonly class`.
- `direction` is validated against `['asc', 'desc']` allowlist at construction.
- The expression carries:
  - `string $property` — entity property name (must pass `IdentifierValidator::isValidIdentifier`).
  - `string $column` — entity column name (must pass `IdentifierValidator::isValidIdentifier`).
  - `string $jsonColumn` — the JSON column name, defaulting to `'scopes'` (must pass `IdentifierValidator::isValidIdentifier`).
  - `array $paths` — ordered list of `['axis' => string, 'path' => string]` entries in declared-axis-priority + deepest-first walk order. **Each `axis` must pass `IdentifierValidator::isValidIdentifier`. The `path` part may contain `.` separators (e.g. `eu.de`) — each dot-segment must pass the identifier check separately. Renderers compose the JSON key from validated parts.**
  - `string $direction`.
- Interface in `Marko\Scope\Query\ScopeSortRendererInterface` with method `render(ScopeSortExpression $expression): string`.
- Interface PHPDoc documents that renderers must produce a COALESCE expression matching the PHP walker resolution order and that renderers MUST NOT pass the colon-composed scope key (`axis:path`) through `IdentifierValidator::isValidIdentifier` — composition happens after validation.

## Implementation Notes
- `ScopeSortExpression` is a `readonly class` with constructor property promotion.
- Direction validated against `['asc', 'desc']` allowlist, throwing `ScopeConfigurationException` for invalid values.
- `$jsonColumn` defaults to `'scopes'` as a constructor parameter default.
- `ScopeSortRendererInterface` PHPDoc documents COALESCE requirement and walker resolution order matching.
- Tests: `packages/scope/tests/Unit/Query/ScopeSortExpressionTest.php` and `packages/scope/tests/Unit/Query/ScopeSortRendererInterfaceTest.php`.
