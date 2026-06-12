# Task 005: SSE subscription heartbeat + idle timeout

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`SseStream::iterateSubscription()` only checks the timeout inside the `foreach` body — it is evaluated AFTER a message arrives. An idle subscription that yields nothing blocks forever and never emits a heartbeat, unlike the data-provider path which has both. Add idle-timeout and heartbeat handling to the subscription path so an idle connection is bounded and kept alive, mirroring `iterateDataProvider()`.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/sse/src/SseStream.php` (`iterateSubscription()` ~59-74; compare `iterateDataProvider()` for the heartbeat/timeout pattern)
  - `/Users/markshust/Sites/marko/packages/pubsub/src/Subscription.php` (interface: `IteratorAggregate<int, Message>`, `getIterator(): Generator`, `cancel()`)
  - Tests: `/Users/markshust/Sites/marko/packages/sse/tests/SseStreamTest.php`
- Patterns to follow:
  - `SseStream` is a `readonly class` with constructor defaults `heartbeatInterval = 15`, `timeout = 300`, `pollInterval = 1`. Keep these constructor-driven (no new config file needed for `sse`).
  - CRITICAL — `foreach` cannot observe idle ticks. The current `foreach ($this->subscription as $message)` blocks inside the backend generator until a message arrives, so there is no point at which the loop can check the clock or emit a heartbeat while idle. Two acceptable shapes; pick one and document it:
    - (a) Treat `null` yields as idle signals: iterate manually via `$iterator = $this->subscription->getIterator();` and advance with `current()`/`next()`, where a yielded `null` (no message available) is an idle tick on which you check timeout and emit a heartbeat. NOTE: `Subscription::getIterator(): Generator<int, Message>` is typed to yield `Message`, never `null` — if you adopt this shape you are widening the contract informally; only the SseStream consumer relies on it, but call it out in implementation notes.
    - (b) Keep `foreach` but treat the gap BETWEEN real messages plus a bounded poll as the tick, mirroring `iterateDataProvider()`'s `do { ... sleep($pollInterval); } while(true)` structure, pulling at most one message per tick. This requires manual iterator advancement too, because a `foreach` body only runs when a message exists.
    - In BOTH shapes you must guard against `null`/non-`Message` values before `new SseEvent(data: $message->payload, event: $message->channel)` — a `null` payload access is a fatal error.
  - On each idle tick: if `time() - $startTime >= $timeout` return; if `time() - $lastActivity >= $heartbeatInterval` yield `": keepalive\n\n"` and reset `$lastActivity`. Reset `$lastActivity` (and the heartbeat baseline) whenever a real message is yielded — matching `iterateDataProvider()`.
  - Test WITHOUT a real clock or socket. Use a fake `Subscription` (anonymous `readonly class implements Subscription`) whose `getIterator()` is a generator yielding a bounded, deterministic sequence that includes idle gaps (e.g. yields a `Message`, then yields `null` several times, then ends). Because real `sleep()` makes tests slow/flaky, the implementation should either inject `pollInterval = 0` in the test or the test should assert on the SEQUENCE of yielded strings (a `: keepalive` comment appears after the configured idle gap and a real `data:`/`event:` block appears for the message) and that iteration TERMINATES — assert ordering/termination, not wall-clock timing. Document how the test makes time observable (e.g. a `Message` count that triggers timeout deterministically) so it is not clock-dependent.
  - `Message` shape: `Subscription` yields `Marko\PubSub\Message` with at least `->payload` and `->channel`. Read `/Users/markshust/Sites/marko/packages/pubsub/src/Message.php` to confirm field names/types before building the fake; the fake's yielded objects must be real `Message` instances (or satisfy the same shape) so the existing happy-path test still passes.
  - Do not break the existing happy-path: real messages still format and yield via `SseEvent`.

## Requirements (Test Descriptions)
- [x] `it yields a formatted event for each message from the subscription`
- [x] `it stops iterating the subscription once the timeout is exceeded`
- [x] `it emits a keepalive heartbeat when the subscription is idle past the heartbeat interval`
- [x] `it resets the heartbeat timer after a real message is yielded`
- [x] `it cancels the subscription when close is called`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Replaced `foreach ($this->subscription as $message)` with manual iterator advancement via `$iterator->rewind()` / `while ($iterator->valid())` / `$iterator->next()`.
- Idle ticks are modelled by the fake `Subscription` in tests yielding `null`; the real `Subscription` interface is typed `Generator<int, Message>` (never `null`) so the `if ($message !== null)` guard is a defensive check for test fakes and malformed backends — not a contract widening used in production.
- Shape chosen: (a) null-as-idle-tick. Fake subscriptions yield `null` to simulate no-message ticks; real subscriptions simply finish their generators normally.
- Heartbeat is emitted when `time() - $lastActivity >= $heartbeatInterval` on idle ticks; `$lastActivity` is reset to `time()` after every real message yield, matching `iterateDataProvider()` behavior.
- Timeout check is at the top of each while-loop iteration so an idle subscription is bounded even when no messages ever arrive.
- Tests use `pollInterval: 0` and `heartbeatInterval: 0` (or large values) to make timing deterministic without wall-clock dependency.
