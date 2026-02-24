# Task 003: PolicyInterface and Policy Resolution

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the policy system for entity-scoped authorization. Policies are classes with methods named after abilities (e.g., `view`, `update`, `delete`). A `PolicyRegistry` maps entity classes to their policy classes. Policy methods receive the user and entity instance.

## Context
- Related files: `packages/authorization/src/` (from task 001)
- Policy methods: `methodName(?AuthorizableInterface $user, object $entity): bool`
- Convention: ability names map directly to method names on the policy class
- Policies are resolved from the container for dependency injection
- Patterns to follow: Container-based resolution, explicit registration

## Requirements (Test Descriptions)
- [ ] `it registers a policy class for an entity class`
- [ ] `it resolves the policy class for a given entity`
- [ ] `it returns null when no policy is registered for an entity`
- [ ] `it checks if a policy has a method for the given ability`
- [ ] `it calls the policy method with user and entity`
- [ ] `it returns the boolean result from the policy method`
- [ ] `it throws AuthorizationException when policy method does not exist`
- [ ] `it prevents registering duplicate policies for the same entity`

## Acceptance Criteria
- All requirements have passing tests
- PolicyRegistry is a standalone class (not coupled to Gate yet)
- Policy classes are resolved from the container
- Clear error messages when policy/method not found

## Implementation Notes
(Left blank - filled in by programmer during implementation)
