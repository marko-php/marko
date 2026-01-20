# Task 003: ErrorHandlerInterface Contract

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Define the ErrorHandlerInterface contract that all error handlers must implement. This interface defines the core API for handling errors and exceptions in the Marko framework.

## Context
- Related files: `packages/errors/src/Contracts/ErrorHandlerInterface.php`
- Patterns to follow: Existing interfaces in core (ContainerInterface, EventDispatcherInterface)
- Keep interface minimal - only essential methods

## Requirements (Test Descriptions)
- [ ] `it defines handle method that accepts ErrorReport`
- [ ] `it defines handle method that returns void`
- [ ] `it defines handleException method that accepts Throwable`
- [ ] `it defines handleError method for PHP errors with standard signature`
- [ ] `it defines register method to register with PHP handlers`
- [ ] `it defines unregister method to restore previous handlers`

## Acceptance Criteria
- All requirements have passing tests
- Interface is minimal and focused
- Documented with PHPDoc
- Code follows project standards
