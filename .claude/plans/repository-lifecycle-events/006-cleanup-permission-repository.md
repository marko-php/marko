# Task 006: Clean Up PermissionRepository Constructor

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove PermissionRepository's constructor override. It injects `PermissionRegistryInterface` (NOT EventDispatcherInterface), but this can be handled differently. The registry is only used in `syncFromRegistry()` — consider whether it should stay as a constructor dep or if `syncFromRegistry()` should accept it as a parameter.

## Context
- Related files: `packages/admin-auth/src/Repository/PermissionRepository.php`
- Constructor adds `?PermissionRegistryInterface $permissionRegistry = null`
- Only used in `syncFromRegistry()` method
- Unlike EventDispatcherInterface, this is NOT a common pattern — it's unique to PermissionRepository
- Option 1: Keep the constructor override (it's a genuinely unique dependency)
- Option 2: Make `syncFromRegistry(PermissionRegistryInterface $registry)` accept it as a method parameter — eliminates the constructor but changes the API
- **Decision**: Option 2 is cleaner — `syncFromRegistry` is a specific operation that should receive its dependency. The repository doesn't need the registry for its core CRUD operations.

## Requirements (Test Descriptions)
- [ ] `it constructs PermissionRepository without constructor override`
- [ ] `it syncs permissions from registry when passed as parameter`
- [ ] `it finds permissions by key`
- [ ] `it finds permissions by group`
- [ ] `it updates PermissionRepositoryInterface to accept registry parameter`

## Implementation Notes

### Interface Change Required
`PermissionRepositoryInterface` at `packages/admin-auth/src/Repository/PermissionRepositoryInterface.php` line 37 currently defines:
```php
public function syncFromRegistry(): void;
```
This MUST change to:
```php
public function syncFromRegistry(PermissionRegistryInterface $registry): void;
```

### Files That Must Change
- `packages/admin-auth/src/Repository/PermissionRepositoryInterface.php` -- update method signature
- `packages/admin-auth/src/Repository/PermissionRepository.php` -- update implementation + remove constructor
- `packages/admin-auth/tests/Unit/Repository/PermissionRepositoryInterfaceTest.php` -- update signature test (line 70-72 verifies zero parameters)
- `packages/admin-auth/tests/Unit/Repository/PermissionRepositoryTest.php` -- update `syncFromRegistry()` calls to pass registry (line 137)

## Acceptance Criteria
- All requirements have passing tests
- PermissionRepository has no constructor override
- `PermissionRepositoryInterface::syncFromRegistry()` accepts `PermissionRegistryInterface` as parameter
- `PermissionRepository::syncFromRegistry()` implementation updated to match
- `PermissionRepositoryInterfaceTest` updated to verify new signature
- Existing PermissionRepository tests still pass
- All callers of `syncFromRegistry()` updated to pass the registry
