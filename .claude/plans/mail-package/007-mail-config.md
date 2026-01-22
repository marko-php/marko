# Task 007: MailConfig Class

**Status**: completed
**Depends on**: 001 (completed)
**Retry count**: 0

## Description
Create the MailConfig class for loading mail configuration from config/mail.php.

## Context
- Loads driver, from address/name, and driver-specific options
- Uses marko/config for loading (Config class)
- Provides typed accessors for configuration values
- Throws MailException if config file not found

## Requirements (Test Descriptions)
- [x] `MailConfig loads driver setting`
- [x] `MailConfig loads from address`
- [x] `MailConfig loads from name`
- [x] `MailConfig provides driver-specific config`
- [x] `MailConfig throws on missing config file`

## Implementation Notes
- Created `Marko\Mail\Config\MailConfig` class with typed accessors
- Added `marko/config` as a dependency in composer.json
- Created default `config/mail.php` configuration file
- Used `ConfigRepositoryInterface` for accessing configuration values
- Added `ensureConfigExists()` method that throws `MailException::configFileNotFound()` when mail config is missing
