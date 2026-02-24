# Task 013: Refactor core package tests — QueueInterface stubs

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace 3 QueueInterface anonymous stubs in EventDispatcherTest with FakeQueue. These are simple capture stubs that record pushed jobs — FakeQueue handles this perfectly. Add `marko/testing` to core's require-dev.

## Context
- Related files:
  - `packages/core/tests/Unit/Event/EventDispatcherTest.php` — 3 anonymous QueueInterface stubs (lines 236, 324, 416)
  - `packages/core/composer.json` — add marko/testing to require-dev

### Current Pattern
```php
$queue = new class () implements QueueInterface {
    public array $jobs = [];
    public function push(JobInterface $job, ?string $queue = null): string {
        $id = uniqid();
        $this->jobs[] = $job;
        return $id;
    }
    // ... 6 more methods
};
```

### Replacement
```php
$queue = new FakeQueue();
// After dispatch:
expect($queue->pushed)->toHaveCount(1);
// Access job: $queue->pushed[0]['job']
```

### Property Changes
| Old | New |
|---|---|
| `$queue->jobs` (simple array of JobInterface) | `$queue->pushed` (array of records: `['job' => ..., 'queue' => ..., 'delay' => ..., 'id' => ...]`) |
| `$queue->jobs[0]` (JobInterface directly) | `$queue->pushed[0]['job']` (access via record key) |

### Note on Dev Dependencies
Adding marko/testing to core's require-dev pulls in 7 transitive packages (config, mail, queue, session, log, authentication). This is acceptable for dev-only and consistent with the framework's testing strategy.

## Requirements (Test Descriptions)
- [ ] `it uses FakeQueue instead of anonymous QueueInterface in EventDispatcherTest (3 replacements)`
- [ ] `it preserves all async observer dispatch test behaviors`

## Acceptance Criteria
- All existing core package tests pass unchanged
- 3 anonymous QueueInterface stubs removed from EventDispatcherTest
- `marko/testing` added to `packages/core/composer.json` require-dev
- Run: `./vendor/bin/pest packages/core/tests/ --parallel`
