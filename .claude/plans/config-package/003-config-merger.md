# Task 003: ConfigMerger

## Status
pending

## Depends On
001

## Description
Implement the ConfigMerger class that handles deep array merging with proper type handling.

## Requirements
- [ ] Create `ConfigMerger` class at `packages/config/src/ConfigMerger.php`
- [ ] Implement `merge(array $base, array $override): array` method:
  - Scalar values: override wins
  - Indexed arrays (numeric keys): override replaces entirely
  - Associative arrays: recursively merged
  - Null values in override: removes the key from result
- [ ] Implement `mergeAll(array ...$configs): array` method for merging multiple arrays
- [ ] Class should be readonly (stateless utility)
- [ ] Unit tests covering:
  - Scalar value override
  - Indexed array replacement
  - Associative array deep merge
  - Null value key removal
  - Multiple levels of nesting
  - Mixed indexed and associative arrays
  - Empty arrays
  - Merging more than two arrays

## Implementation Notes
<!-- Notes added during implementation -->
