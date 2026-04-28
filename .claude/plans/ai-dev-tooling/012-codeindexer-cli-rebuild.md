# Task 012: Add indexer:rebuild CLI command

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Add an `#[Command]` class to `marko/codeindexer` that rebuilds the index on demand. Invoked via `marko indexer:rebuild` by users and by CI.

## Context
- Namespace: `Marko\CodeIndexer\Commands\RebuildIndexCommand`
- Attribute: `#[Command(name: 'indexer:rebuild')]` (from `marko/cli`)
- Package adds `marko/cli` to composer require (not present in skeleton task 005)
- Prints summary of discovered counts (modules, observers, plugins, etc.) and cache file location on success

## Requirements (Test Descriptions)
- [ ] `it is registered via the Command attribute with name indexer:rebuild`
- [ ] `it invokes IndexCache::rebuild and writes to disk`
- [ ] `it prints a summary of indexed counts`
- [ ] `it prints the cache file path on success`
- [ ] `it exits with non-zero and a helpful message on failure`

## Acceptance Criteria
- Command discoverable via `marko list`
- Integration test confirms end-to-end rebuild in a fixture app

## Implementation Notes
(Filled in by programmer during implementation)
