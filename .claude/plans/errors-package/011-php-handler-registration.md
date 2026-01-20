# Task 011: PHP Error/Exception Handler Registration

**Status**: completed
**Depends on**: 010
**Retry count**: 0

## Description
Implement the register and unregister methods on SimpleErrorHandler that hook into PHP's native error and exception handling. This allows the handler to capture all errors and exceptions automatically.

## Context
- Related files: `packages/errors-simple/src/SimpleErrorHandler.php`
- Uses: set_error_handler, set_exception_handler, register_shutdown_function
- Must restore previous handlers on unregister

## Requirements (Test Descriptions)
- [ ] `it registers as PHP exception handler`
- [ ] `it registers as PHP error handler`
- [ ] `it registers shutdown function for fatal errors`
- [ ] `it stores previous exception handler on register`
- [ ] `it stores previous error handler on register`
- [ ] `it restores previous exception handler on unregister`
- [ ] `it restores previous error handler on unregister`
- [ ] `it handles fatal errors via shutdown function`
- [ ] `it detects fatal error types in shutdown function`
- [ ] `it only handles fatal error once in shutdown`
- [ ] `it tracks registration state`
- [ ] `it prevents double registration`
- [ ] `it allows re-registration after unregister`

## Acceptance Criteria
- All requirements have passing tests
- Cleanly integrates with PHP error system
- Properly restores previous handlers
- Code follows project standards
