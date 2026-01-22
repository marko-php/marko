# Task 007: MailConfig Class

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the MailConfig class for loading mail configuration from config/mail.php.

## Context
- Loads driver, from address/name, and driver-specific options
- Uses marko/config for loading
- Provides typed accessors for configuration values
- Throws MailException if config file not found

## Requirements (Test Descriptions)
- [ ] `MailConfig loads driver setting`
- [ ] `MailConfig loads from address`
- [ ] `MailConfig loads from name`
- [ ] `MailConfig provides driver-specific config`
- [ ] `MailConfig throws on missing config file`

## Implementation Notes
