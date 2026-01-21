# Task 002: Connection Interfaces and Config

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the connection interfaces and configuration classes that define the contract for database connections. This includes ConnectionInterface, TransactionInterface, and DatabaseConfig. Also implement the "no driver installed" loud error.

## Context
- Related files: packages/database/src/Connection/, config/database.php
- Patterns to follow: Interface patterns from marko/errors package
- Config follows Marko's PHP-only configuration approach

## Requirements (Test Descriptions)
- [ ] `it defines ConnectionInterface with connect, disconnect, and isConnected methods`
- [ ] `it defines ConnectionInterface with query and execute methods`
- [ ] `it defines ConnectionInterface with prepare and statement execution`
- [ ] `it defines TransactionInterface with begin, commit, and rollback methods`
- [ ] `it defines TransactionInterface with transaction callback method`
- [ ] `it creates DatabaseConfig that reads from config/database.php`
- [ ] `it throws ConfigurationException when config file is missing`
- [ ] `it throws ConfigurationException when required keys are missing`
- [ ] `it throws DatabaseException with helpful message when no driver is installed`
- [ ] `it includes suggestion to install driver package in exception message`

## Acceptance Criteria
- All requirements have passing tests
- Interfaces are minimal and focused
- Config class validates required fields
- No driver error is loud with context and suggestion

## Implementation Notes
(Left blank - filled in by programmer during implementation)
