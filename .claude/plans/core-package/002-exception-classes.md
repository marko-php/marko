# Task 002: Exception Classes

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the exception classes that provide loud, helpful error messages. These exceptions are fundamental to Marko's "fail loud" philosophy and will be used throughout the core package.

## Context
- Location: `packages/core/src/Exceptions/`
- Each exception should provide clear description, context, and fix suggestions
- Follow the "Every Error Provides" philosophy from architecture

## Requirements (Test Descriptions)
- [ ] `it throws BindingException when no implementation exists for interface`
- [ ] `it throws BindingConflictException when multiple modules bind same interface`
- [ ] `it throws ModuleException when module manifest is invalid`
- [ ] `it throws CircularDependencyException when modules have circular dependencies`
- [ ] `it throws PluginException when plugin configuration is invalid`
- [ ] `it includes helpful message with what went wrong in all exceptions`
- [ ] `it includes context about where error occurred in all exceptions`
- [ ] `it includes suggestion for how to fix in all exceptions`

## Acceptance Criteria
- All requirements have passing tests
- Each exception extends a base MarkoException
- Exception messages are clear and actionable
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Exceptions/
  MarkoException.php
  BindingException.php
  BindingConflictException.php
  ModuleException.php
  CircularDependencyException.php
  PluginException.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
