# Task 009: marko/admin-auth - AdminUserProvider implementing UserProviderInterface

**Status**: complete
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
- [x] `it implements UserProviderInterface`
- [x] `it retrieves admin user by id via retrieveById`
- [x] `it returns null from retrieveById when user not found`
- [x] `it returns null from retrieveById when user is inactive`
- [x] `it retrieves admin user by email credentials via retrieveByCredentials`
- [x] `it returns null from retrieveByCredentials when email not found`
- [x] `it validates credentials using PasswordHasherInterface`
- [x] `it returns false from validateCredentials when password is wrong`
- [x] `it loads roles and permissions when retrieving a user`
- [x] `it retrieves user by remember token via retrieveByRememberToken`
- [x] `it updates remember token via updateRememberToken`

## Acceptance Criteria
- All requirements have passing tests
- Provider follows UserProviderInterface contract exactly
- Only active users are returned
- Roles and permissions are eager-loaded
- Code follows code standards
