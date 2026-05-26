# Task 006: ReplicaSelectorInterface + Random + Weighted Implementations

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Define `ReplicaSelectorInterface` and ship two implementations: `RandomReplicaSelector` (uniform random) and `WeightedReplicaSelector` (probability proportional to integer weights). The selector is the extension seam for future replica-selection strategies and is the only thing `ReadWriteConnection` consults to pick a replica for each read.

## Context
- **File locations:**
  - `packages/database-readwrite/src/Replica/ReplicaSelectorInterface.php`
  - `packages/database-readwrite/src/Replica/RandomReplicaSelector.php`
  - `packages/database-readwrite/src/Replica/WeightedReplicaSelector.php`
  - `packages/database-readwrite/tests/Unit/Replica/RandomReplicaSelectorTest.php`
  - `packages/database-readwrite/tests/Unit/Replica/WeightedReplicaSelectorTest.php`
- **Interface shape (lock):**
  ```php
  namespace Marko\Database\ReadWrite\Replica;

  use Marko\Database\Connection\ConnectionInterface;

  interface ReplicaSelectorInterface
  {
      /**
       * Pick a replica for the next read query.
       *
       * The returned object MUST be identity-equal (===) to one of the elements
       * returned by all() — the fallback loop in ReadWriteConnection compares by
       * object identity to skip already-tried replicas.
       *
       * @return ConnectionInterface The chosen replica connection
       */
      public function select(): ConnectionInterface;

      /**
       * Return every replica this selector can choose from.
       *
       * The returned list MUST be stable across calls within a single request:
       * the same replicas in the same order. (Implementations typically return a
       * reference to a constructor-stored array — stability is automatic.)
       *
       * @return list<ConnectionInterface> All replicas this selector can choose from, in stable order
       */
      public function all(): array;
  }
  ```
- **Why `all()` is on the interface:** Single-request fallback (task 010) needs to iterate ALL replicas when one fails. Putting it on the interface keeps the fallback strategy-agnostic.
- **Contract guarantees** (test these explicitly):
  - `select()` always returns an object that is `===` to one of the elements in `all()`.
  - Two consecutive `all()` calls return the same objects in the same order.
- **RandomReplicaSelector:**
  - Constructor takes `array $replicas` (list of `ConnectionInterface`).
  - `select()` uses `random_int(0, count - 1)` to pick.
  - `all()` returns the replicas array as-is.
  - Throws `LogicException` (or a Marko exception) if constructed with an empty array — but really, config validation should prevent this; defensive throw is fine.
- **WeightedReplicaSelector:**
  - Constructor takes `array $replicas` AND `array $weights` (parallel lists; both indexed 0..N-1).
  - `select()` computes `total = sum($weights)`, picks `$random = random_int(1, $total)`, walks the weights cumulatively until `$cumulative >= $random`, returns the matching replica.
  - `all()` returns the replicas array as-is.
  - Throws if weights array length differs from replicas length, or if any weight is ≤ 0.
- **Tests for distribution:** Run `select()` 10,000 times and assert observed distribution is within tolerance (±3%) of expected. Use `srand(<fixed seed>)` if needed for reproducibility — though `random_int()` doesn't honor seeding. Better: run enough iterations that statistical noise washes out and assert within tolerance.
- **Stub the ConnectionInterface for tests** with minimal anonymous classes or a tiny named stub. Do NOT instantiate real driver connections in selector tests.

## Requirements (Test Descriptions)
- [ ] `RandomReplicaSelector returns the single replica when constructed with one`
- [ ] `RandomReplicaSelector distributes selections roughly evenly across multiple replicas`
- [ ] `RandomReplicaSelector exposes all replicas via all()`
- [ ] `RandomReplicaSelector throws when constructed with an empty replica list`
- [ ] `WeightedReplicaSelector returns the single replica when constructed with one`
- [ ] `WeightedReplicaSelector distributes selections proportional to weights within tolerance`
- [ ] `WeightedReplicaSelector with equal weights behaves like random`
- [ ] `WeightedReplicaSelector exposes all replicas via all()`
- [ ] `WeightedReplicaSelector throws when weights array length does not match replicas length`
- [ ] `WeightedReplicaSelector throws when any weight is zero or negative`
- [ ] `select() returns an object identity-equal to one of the elements of all()` (both selectors)
- [ ] `two consecutive all() calls return the same objects in the same order` (both selectors)

## Acceptance Criteria
- Three new src files + two new test files.
- Interface + both implementations follow code-standards.md (strict types, readonly, type declarations).
- Distribution tests use ≥ 10,000 iterations and a tolerance of ±3% per bucket.
- `composer test` passes.
- `./vendor/bin/phpcs packages/database-readwrite/` and `./vendor/bin/php-cs-fixer fix packages/database-readwrite/ --dry-run --diff` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
