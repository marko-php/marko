# Task 002: Exception Classes

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create routing-specific exception classes that provide loud, helpful error messages following the MarkoException pattern. These include RouteException for general routing errors and RouteConflictException for duplicate route definitions.

## Context
- Location: `packages/routing/src/Exceptions/`
- Extend MarkoException from core for consistent error format
- Follow the pattern established in core (message, context, suggestion)
- Errors should guide developers toward resolution

## Requirements (Test Descriptions)
- [ ] `it creates RouteException extending MarkoException`
- [ ] `it creates RouteConflictException extending MarkoException`
- [ ] `RouteException::ambiguousOverride provides helpful message for method override without attribute`
- [ ] `RouteException::invalidParameter provides helpful message for malformed route parameters`
- [ ] `RouteException::controllerNotFound provides helpful message when controller class missing`
- [ ] `RouteException::methodNotFound provides helpful message when method missing`
- [ ] `RouteConflictException::duplicateRoute shows both conflicting routes with paths and controllers`
- [ ] `RouteConflictException includes suggestion to use Preference or change path`

## Acceptance Criteria
- All requirements have passing tests
- Error messages include what went wrong, where, and how to fix
- Exceptions follow MarkoException pattern

## Files to Create
```
packages/routing/src/Exceptions/
  RouteException.php
  RouteConflictException.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
