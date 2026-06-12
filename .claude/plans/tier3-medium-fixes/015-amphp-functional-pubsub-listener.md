# Task 015: amphp pubsub:listen becomes a functional listener with graceful shutdown

**Status**: complete
**Depends on**: [016]
**Retry count**: 0

## Description
`pubsub:listen` is currently pseudo-functionality. `PubSubListenCommand::execute()` prints "Starting..." then calls `EventLoopRunner::run()`, which runs an EMPTY `Revolt\EventLoop` (`EventLoop::run()` with nothing registered) and returns immediately — no subscription, no dispatch, no signal handling. The `amphp.shutdown_timeout` config and `AmphpConfig::shutdownTimeout()` are dead code (never consulted), and there is no SIGINT handler despite the "Press Ctrl+C to stop" prompt.

DEFAULT APPROACH — implement a real listener: subscribe via the pubsub `SubscriberInterface` driver to the configured channel(s), register the resulting `Subscription`'s message stream on the amphp `EventLoop`, dispatch each received `Message`, and install a real SIGINT/graceful-shutdown path that uses the `shutdown_timeout` config to bound how long in-flight work may finish before the loop is stopped.

## Context
- Related files:
  - `packages/amphp/src/Command/PubSubListenCommand.php` (lines 13-33 — `#[Command(name: 'pubsub:listen')]`, `execute()` writes two lines, calls `$this->runner->run()`, writes "Listener stopped.")
  - `packages/amphp/src/EventLoopRunner.php` (lines 9-43 — `run()`/`stop()`/`isRunning()` flag plumbing; `doRun()` = `EventLoop::run()`, `doStop()` = `EventLoop::getDriver()->stop()`)
  - `packages/amphp/src/AmphpConfig.php` (`shutdownTimeout(): int` → `config->getInt('amphp.shutdown_timeout')`; **ADD a `channels(): array` getter** → `config->getArray('amphp.channels')`, throws `ConfigNotFoundException` when missing — no hardcoded fallback)
  - `packages/amphp/config/amphp.php` (currently has ONLY `'shutdown_timeout'` — **ADD a `'channels' => [...]` key**; channel defaults belong here, not in code)
  - `packages/amphp/composer.json` (**ADD `"marko/pubsub": "self.version"` to require** — the package does NOT depend on `marko/pubsub` today, so `SubscriberInterface` is not autoloadable/type-hintable without this)
  - `packages/amphp/module.php` (binding/registration — wire the new dependencies here)
  - pubsub contract: `packages/pubsub/src/SubscriberInterface.php` (`subscribe(string ...$channels): Subscription`), `packages/pubsub/src/Subscription.php` (`IteratorAggregate<int, Message>`, `getIterator(): Generator`, `cancel(): void`), `packages/pubsub/src/Message.php`
  - `packages/amphp/src/Exceptions/AmphpException.php` (currently `extends MarkoException` with no factories — add factories for any new loud-error path, e.g. no channels configured)
- Patterns to follow:
  - **Prerequisite wiring (verified missing at review time):** add `"marko/pubsub": "self.version"` to `amphp/composer.json`, add the `'channels'` config key to `config/amphp.php`, and add `AmphpConfig::channels()`. Without these the listener cannot type-hint `SubscriberInterface` or read its channel list.
  - Constructor inject `SubscriberInterface`, `AmphpConfig`, and the message-dispatch target. Read the channel list from `AmphpConfig::channels()` (no hardcoded channel names in code).
  - Use `EventLoop::queue()`/`EventLoop::onSignal(SIGINT, ...)` (Revolt) to register the subscription consumer and the shutdown handler. On signal, call the subscription's `cancel()` and stop the loop within `shutdownTimeout()` seconds.
  - Dispatch each `Message`: emit to the framework's event/dispatch seam or invoke a configured handler — surface the received message in a way the test can assert. Keep the dispatch target explicit (constructor-injected), not a service-locator lookup.
  - Loud errors: if no channels are configured, throw an `AmphpException` factory (message/context/suggestion) rather than silently running an idle loop.

### DEPENDS ON Task 016
This task subscribes through the pubsub driver and relies on `subscribe('a','b', ...)` actually registering ALL channels. Task 016 fixes the redis/pgsql drivers that currently drop all-but-the-first channel and serialize pgsql delivery. Build/rebase 015 on top of 016 so the functional listener exercises a correct multi-channel `Subscription`.

### Implementation note — ALTERNATIVE (product decision, confirm with maintainer)
If a functional async listener is out of scope at implementation time, the no-pseudo-functionality principle says do NOT ship an empty loop: instead REMOVE the `pubsub:listen` command, `EventLoopRunner`, and the dead `amphp.shutdown_timeout` config + `AmphpConfig::shutdownTimeout()`. This is a product decision and MUST be confirmed with the maintainer before choosing the removal path over the implementation path. The plan flags this in Risks & Notes.

### Verification note (read at planning time)
Confirmed: `EventLoopRunner::doRun()` calls `EventLoop::run()` with nothing registered; `PubSubListenCommand` never touches `AmphpConfig` or any subscriber; no `onSignal` anywhere in the package. `shutdownTimeout()` and its config key have no callers.

## Requirements (Test Descriptions)
- [x] `it subscribes to the configured pub/sub channels`
- [x] `it dispatches a received message to the configured handler`
- [x] `it throws AmphpException when no channels are configured`
- [x] `it stops the listener on shutdown signal`
- [x] `it bounds graceful shutdown by the configured shutdown_timeout`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Created `MessageHandlerInterface` at `packages/amphp/src/Command/MessageHandlerInterface.php` — the explicit, constructor-injected dispatch target
- Added `channels(): array` getter to `AmphpConfig` reading `amphp.channels` config key
- Added `'channels'` key to `config/amphp.php` parsed from `AMPHP_CHANNELS` env var (comma-separated)
- Added `noChannelsConfigured()` factory method to `AmphpException` with message/context/suggestion
- Added `queue()`, `onSignal()`, and `delay()` methods to `EventLoopRunner` — these delegate to `EventLoop::*` static calls but are overridable for testing
- Updated `PubSubListenCommand` to inject `AmphpConfig`, `SubscriberInterface`, and `MessageHandlerInterface`; validates channels, subscribes, queues message processing, installs SIGINT handler with graceful shutdown bounded by `shutdownTimeout()`
- Added `"marko/pubsub": "self.version"` to `packages/amphp/composer.json`
- All 5 requirements tested via injected fakes and an overridable `TestEventLoopRunner` — no live event loop or socket needed
