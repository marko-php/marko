# Task 004: ConfigLoader

## Status
pending

## Depends On
002

## Description
Implement the ConfigLoader class that loads and validates PHP configuration files.

## Requirements
- [ ] Create `ConfigLoader` class at `packages/config/src/ConfigLoader.php`
- [ ] Implement `load(string $filePath): array` method:
  - Validates file exists (throws ConfigLoadException if not)
  - Includes PHP file using `require`
  - Validates returned value is an array (throws ConfigLoadException if not)
  - Catches ParseError and converts to ConfigLoadException with helpful message
  - Returns the configuration array
- [ ] Implement `loadIfExists(string $filePath): ?array` method:
  - Returns null if file doesn't exist
  - Otherwise same as `load()`
- [ ] Class should be readonly
- [ ] Unit tests covering:
  - Loading valid config file
  - File not found error
  - File returns non-array error
  - PHP syntax error handling
  - loadIfExists with existing file
  - loadIfExists with non-existing file

## Implementation Notes
<!-- Notes added during implementation -->
