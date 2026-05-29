# Marko Framework Core Guidelines

## Strict Types
Every PHP file must declare `declare(strict_types=1);`.

## Constructor Property Promotion
Use constructor property promotion always. Avoid traditional property + assignment patterns.

## No Final Classes
Marko's Preferences system requires extensibility. Do not mark classes as `final`.

## No Magic Methods
Avoid `__get`, `__set`, `__call`. Be explicit.

## Type Declarations
All parameters, return types, and properties require type declarations. Use the narrowest type.

## Loud Errors
All exceptions extend `MarkoException` with `message`, `context`, `suggestion` named parameters.

## See Also
- Architecture: `.claude/architecture.md`
- Testing: `.claude/testing.md`
- Code Standards: `.claude/code-standards.md`
