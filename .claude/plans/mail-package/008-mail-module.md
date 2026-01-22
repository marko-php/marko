# Task 008: Mail Package module.php

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Create the module.php for the mail package with MailConfig binding.

## Context
- Binds MailConfig to itself for DI resolution
- Does NOT bind MailerInterface (drivers do that)
- Standard Marko module structure

## Requirements (Test Descriptions)
- [ ] `module.php exists with correct structure`
- [ ] `module.php binds MailConfig`
- [ ] `module.php does not bind MailerInterface`

## Implementation Notes
