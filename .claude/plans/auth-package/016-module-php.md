# Task 016: module.php with Bindings

**Status**: pending
**Depends on**: 010, 015
**Retry count**: 0

## Description
Create module.php with DI bindings for auth components.

## Context
- Binds interfaces to implementations
- Uses factory closures for complex instantiation
- Follows existing module.php patterns

## Requirements (Test Descriptions)
- [ ] `it has enabled set to true`
- [ ] `it has bindings array`
- [ ] `it binds PasswordHasherInterface to BcryptPasswordHasher`
- [ ] `it binds AuthManager with factory`
- [ ] `it creates password hasher with config cost`
- [ ] `it creates auth manager with container and config`

## Acceptance Criteria
- All requirements have passing tests
- Follows existing module.php patterns
- Factory closures properly typed

## Implementation Notes
(Left blank - filled in by programmer during implementation)
