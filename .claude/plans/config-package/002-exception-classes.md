# Task 002: Exception Classes

## Status
pending

## Depends On
001

## Description
Create the exception hierarchy for the config package with helpful error messages following Marko's loud errors principle.

## Requirements
- [ ] Create `ConfigException` as base exception extending `MarkoException`:
  - Located at `packages/config/src/Exceptions/ConfigException.php`
  - Accepts message, context, and suggestion parameters
- [ ] Create `ConfigNotFoundException` extending `ConfigException`:
  - Located at `packages/config/src/Exceptions/ConfigNotFoundException.php`
  - Used when required config key is not found
  - Message should include the key that was requested
  - Suggestion should guide user to check config files or add default value
- [ ] Create `ConfigLoadException` extending `ConfigException`:
  - Located at `packages/config/src/Exceptions/ConfigLoadException.php`
  - Used when config file cannot be loaded (syntax error, missing file, etc.)
  - Message should include file path
  - Context should include parse error details if applicable
- [ ] All exceptions use strict types
- [ ] All exceptions follow Marko code standards (constructor property promotion, multiline params)
- [ ] Unit tests for all exception classes

## Implementation Notes
<!-- Notes added during implementation -->
