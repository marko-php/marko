# Task 009: marko/admin-auth - AdminUserProvider implementing UserProviderInterface

**Status**: pending
**Depends on**: 006, 008
**Retry count**: 0

## Description
Create `AdminUserProvider` implementing the auth package's `UserProviderInterface`. This provider retrieves admin users from the database, validates credentials using the password hasher, and manages remember tokens. It's the bridge between admin-auth and the existing auth system.

## Context
- Related files: `packages/auth/src/Contracts/UserProviderInterface.php`, `packages/auth/src/Contracts/PasswordHasherInterface.php`
- Provider retrieves AdminUser entities via AdminUserRepository
- Uses PasswordHasherInterface for credential validation
- Only returns active users (`is_active = true`)
- Loads roles and their permissions when retrieving a user
- This provider will be configured as the provider for the `admin` guard

## Requirements (Test Descriptions)
- [ ] `it implements UserProviderInterface`
- [ ] `it retrieves admin user by id via retrieveById`
- [ ] `it returns null from retrieveById when user not found`
- [ ] `it returns null from retrieveById when user is inactive`
- [ ] `it retrieves admin user by email credentials via retrieveByCredentials`
- [ ] `it returns null from retrieveByCredentials when email not found`
- [ ] `it validates credentials using PasswordHasherInterface`
- [ ] `it returns false from validateCredentials when password is wrong`
- [ ] `it loads roles and permissions when retrieving a user`
- [ ] `it retrieves user by remember token via retrieveByRememberToken`
- [ ] `it updates remember token via updateRememberToken`

## Acceptance Criteria
- All requirements have passing tests
- Provider follows UserProviderInterface contract exactly
- Only active users are returned
- Roles and permissions are eager-loaded
- Code follows code standards
