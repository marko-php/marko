# Task 003: Add Module Bindings and Update Blog Suggestions

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create module.php with DI bindings for `MailerInterface` and update the blog package's composer.json to suggest `marko/mail-log` as a driver option.

## Context
- Related files: `packages/mail-smtp/module.php` (pattern to follow), `packages/blog/composer.json`
- The binding should use the storage path from configuration
- Blog should suggest both mail-smtp and mail-log drivers

## Requirements (Test Descriptions)
- [ ] `it binds MailerInterface to LogMailer in module.php`
- [ ] `it resolves LogMailer with configured storage path`
- [ ] `it uses default storage path when not configured`
- [ ] `blog composer.json suggests marko/mail-log driver`
- [ ] `blog composer.json suggests marko/mail-smtp driver`

## Acceptance Criteria
- module.php exists with proper bindings
- LogMailer receives storage path from config or uses sensible default
- Blog's composer.json includes both mail drivers in suggest
- All requirements have passing tests

## Implementation Notes
(Left blank - filled in by programmer during implementation)
