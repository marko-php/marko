# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/layout` package with composer.json, module.php, config file, and exception classes. This establishes the package structure that all subsequent tasks build on.

## Context
- Related files: `packages/view/composer.json`, `packages/view/module.php`, `packages/view/src/Exceptions/` (patterns to follow)
- Namespace: `Marko\Layout`
- Package lives at `packages/layout/`

## Requirements (Test Descriptions)
- [x] `it has a valid composer.json with correct name, dependencies, and autoload`
- [x] `it has a module.php that returns a valid module configuration array`
- [x] `it has a config/layout.php that returns a configuration array`
- [x] `it has a LayoutException base class extending MarkoException`
- [x] `it has a ComponentNotFoundException with a static factory method providing message, context, and suggestion`
- [x] `it has a SlotNotFoundException with a static factory method providing message, context, and suggestion`
- [x] `it has a LayoutNotFoundException with a static factory method providing message, context, and suggestion`
- [x] `it has a DuplicateComponentException with a static factory method providing message, context, and suggestion`
- [x] `it has a CircularSlotException with a static factory method providing message, context, and suggestion`
- [x] `it has an AmbiguousSortOrderException with a static factory method providing message, context, and suggestion`

## Acceptance Criteria
- All requirements have passing tests
- Package structure matches existing packages (view, routing)
- composer.json requires `marko/core`, `marko/view`, `marko/routing`
- Exceptions follow the message/context/suggestion pattern from MarkoException
- No decrease in test coverage

## Implementation Notes
- Created package at `packages/layout/` following view package patterns
- composer.json requires marko/core, marko/view, marko/routing (all self.version)
- module.php returns empty bindings array (no concrete bindings needed at this stage)
- config/layout.php returns empty components and layouts arrays
- LayoutException extends MarkoException as base for all layout exceptions
- Six exception classes created: ComponentNotFoundException, SlotNotFoundException, LayoutNotFoundException, DuplicateComponentException, CircularSlotException, AmbiguousSortOrderException
- All exceptions use named message/context/suggestion parameters via static factory methods
- Added package to root composer.json repositories, require, and autoload-dev sections
- Created .gitattributes and LICENSE files to satisfy packaging test requirements
- Updated hardcoded package counts in PackagingTest.php and IntegrationVerificationTest.php from 71 to 72
