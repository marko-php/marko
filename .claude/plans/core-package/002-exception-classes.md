# Task 002: Exception Classes

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the exception classes that provide loud, helpful error messages. These exceptions are fundamental to Marko's "fail loud" philosophy and will be used throughout the core package.

## Context
- Location: `packages/core/src/Exceptions/`
- Each exception should provide clear description, context, and fix suggestions
- Follow the "Every Error Provides" philosophy from architecture

## Requirements (Test Descriptions)
- [x] `it throws BindingException when no implementation exists for interface`
- [x] `it throws BindingConflictException when multiple modules bind same interface`
- [x] `it throws ModuleException when module manifest is invalid`
- [x] `it throws CircularDependencyException when modules have circular dependencies`
- [x] `it throws PluginException when plugin configuration is invalid`
- [x] `it includes helpful message with what went wrong in all exceptions`
- [x] `it includes context about where error occurred in all exceptions`
- [x] `it includes suggestion for how to fix in all exceptions`

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

All exception classes have been implemented following TDD and the "fail loud" philosophy:

1. **MarkoException** - Base exception class extending PHP's Exception with:
   - Constructor accepting message, context, suggestion, code, and previous exception
   - `getContext()` method for retrieving where the error occurred
   - `getSuggestion()` method for retrieving actionable fix suggestions

2. **BindingException** - For DI container binding errors:
   - `noImplementation()` factory method for missing interface bindings

3. **BindingConflictException** - For binding conflicts:
   - `multipleBindings()` factory method when multiple modules bind the same interface

4. **ModuleException** - For module loading errors:
   - `invalidManifest()` factory method for malformed module.php files

5. **CircularDependencyException** - For dependency graph errors:
   - `detected()` factory method showing the circular dependency chain

6. **PluginException** - For plugin registration errors:
   - `invalidConfiguration()` factory method for invalid plugin setup

All exceptions provide:
- Clear message describing what went wrong
- Context describing where the error occurred
- Actionable suggestion for how to fix the issue
