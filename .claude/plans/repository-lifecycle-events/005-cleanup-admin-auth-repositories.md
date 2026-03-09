# Task 005: Clean Up AdminUserRepository + RoleRepository Constructors

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove constructor overrides from AdminUserRepository and RoleRepository. Both only existed to inject EventDispatcherInterface. Their `save()`/`delete()` overrides stay for domain events.

## Context
- Related files:
  - `packages/admin-auth/src/Repository/AdminUserRepository.php`
  - `packages/admin-auth/src/Repository/RoleRepository.php`
- AdminUserRepository: constructor adds EventDispatcherInterface, save() dispatches AdminUserCreated/AdminUserUpdated, delete() is a pass-through (remove it too)
- RoleRepository: constructor adds EventDispatcherInterface, save() dispatches RoleCreated/RoleUpdated, delete() dispatches RoleDeleted
- Both use `private readonly ?EventDispatcherInterface $eventDispatcher` — after removal, they inherit `protected readonly` from base
- AdminUserRepository.delete() is a pure pass-through (`parent::delete($entity)`) — remove it
- Existing tests: `packages/admin-auth/tests/Unit/AdminUserProviderTest.php`

## Requirements (Test Descriptions)
- [ ] `it constructs AdminUserRepository without explicit EventDispatcherInterface`
- [ ] `it dispatches lifecycle events and AdminUserCreated domain event on new user save`
- [ ] `it dispatches lifecycle events and AdminUserUpdated domain event on existing user save`
- [ ] `it constructs RoleRepository without explicit EventDispatcherInterface`
- [ ] `it dispatches lifecycle events and RoleCreated domain event on new role save`
- [ ] `it dispatches lifecycle events and RoleUpdated domain event on existing role save`
- [ ] `it dispatches RoleDeleted event on role delete`

Note: Both lifecycle events (from base Repository) AND domain events fire. See task 001.

## Acceptance Criteria
- All requirements have passing tests
- Neither repository has a constructor override
- AdminUserRepository.delete() pass-through removed
- save()/delete() overrides with domain logic remain
- Existing admin-auth tests still pass
