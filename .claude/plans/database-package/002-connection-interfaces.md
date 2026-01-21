# Task 002: Connection Interfaces and Config

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the connection interfaces and configuration classes that define the contract for database connections. This includes ConnectionInterface, TransactionInterface, and DatabaseConfig. Also implement the "no driver installed" loud error.

## Context
- Related files: packages/database/src/Connection/, config/database.php
- Patterns to follow: Interface patterns from marko/errors package
- Config follows Marko's PHP-only configuration approach

## Requirements (Test Descriptions)
- [x] `it defines ConnectionInterface with connect, disconnect, and isConnected methods`
- [x] `it defines ConnectionInterface with query and execute methods`
- [x] `it defines ConnectionInterface with prepare and statement execution`
- [x] `it defines TransactionInterface with begin, commit, and rollback methods`
- [x] `it defines TransactionInterface with transaction callback method`
- [x] `it creates DatabaseConfig that reads from config/database.php`
- [x] `it throws ConfigurationException when config file is missing`
- [x] `it throws ConfigurationException when required keys are missing`
- [x] `it throws DatabaseException with helpful message when no driver is installed`
- [x] `it includes suggestion to install driver package in exception message`

## Acceptance Criteria
- All requirements have passing tests
- Interfaces are minimal and focused
- Config class validates required fields
- No driver error is loud with context and suggestion

## Implementation Notes

Created the following files:

**Connection Interfaces:**
- `packages/database/src/Connection/ConnectionInterface.php` - Core connection interface with connect/disconnect/isConnected, query/execute, and prepare methods
- `packages/database/src/Connection/TransactionInterface.php` - Transaction interface with beginTransaction/commit/rollback and transaction callback
- `packages/database/src/Connection/StatementInterface.php` - Prepared statement interface with execute/fetchAll/fetch/rowCount methods

**Configuration:**
- `packages/database/src/Config/DatabaseConfig.php` - Readonly config class that loads from config/database.php with validation

**Exceptions:**
- `packages/database/src/Exceptions/ConfigurationException.php` - Exception for missing config file and missing required keys
- `packages/database/src/Exceptions/DatabaseException.php` - Exception for missing drivers with helpful install suggestions

All exceptions follow the MarkoException pattern with message, context, and suggestion.
