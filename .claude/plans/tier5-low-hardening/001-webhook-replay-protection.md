# Task 001: Webhook inbound replay protection (timestamp freshness window)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
The inbound webhook path (`WebhookReceiver` → `WebhookVerifier`) HMAC-verifies the body in a timing-safe way but has no timestamp/nonce freshness check, so any captured valid request replays forever. Add a signed-timestamp freshness window: the receiver reads an `X-Webhook-Timestamp` header, verifies it falls within a configurable tolerance of the current time (both stale-past AND future-skew), and the HMAC must cover the timestamp so it cannot be altered. Sender (`WebhookSignature`) and receiver must agree on the signed-message format. This is a breaking change to the wire format and to two constructor/method signatures inside the package — both sides and the existing tests change in lockstep, in THIS task.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/webhook/src/Receiving/WebhookReceiver.php` — currently `__construct(WebhookVerifier $verifier)`; `receive()` reads only `X-Webhook-Signature` and calls `verify($body, $signature, $secret)`. Must now ALSO read `X-Webhook-Timestamp`, obtain the tolerance, and pass the timestamp through to the verifier.
  - `/Users/markshust/Sites/marko/packages/webhook/src/Receiving/WebhookVerifier.php` — current signature is `verify(string $body, string $signature, string $secret): bool`. It MUST gain a timestamp parameter and the tolerance (e.g. `verify(string $body, string $timestamp, string $signature, string $secret, int $tolerance): bool`) so the HMAC covers `"$timestamp.$body"` and the freshness window is checked. Decide and DOCUMENT the final signature in the implementation notes; both `WebhookReceiver` and the tests call it.
  - `/Users/markshust/Sites/marko/packages/webhook/src/Sending/WebhookSignature.php` — companion: `sign(string $payload, string $secret)` MUST change to incorporate the timestamp so a freshly-signed payload round-trips through the receiver. Provide a way for the sender to produce BOTH the timestamp header value and the matching signature (e.g. `sign(string $payload, string $secret, int $timestamp): string` returning the `sha256=...` HMAC of `"$timestamp.$payload"`, with the caller responsible for sending `X-Webhook-Timestamp`). Document the chosen API in implementation notes.
  - `/Users/markshust/Sites/marko/packages/webhook/src/Sending/WebhookDispatcher.php` — WIDER BLAST RADIUS (confirmed via source): `dispatch()` at line 27 calls `WebhookSignature::sign($body, $payload->secret)` and sends ONLY `X-Webhook-Signature`. When `sign()` changes, `WebhookDispatcher` MUST: generate a timestamp (`time()`), sign `"$timestamp.$body"`, and add `X-Webhook-Timestamp` to the outgoing headers. Otherwise the package sends webhooks its own receiver will reject — the round-trip success criterion fails. This is in scope for Task 001.
  - `/Users/markshust/Sites/marko/packages/webhook/src/Config/WebhookConfig.php` — IMPORTANT: this class does NOT use lazy getters. It reads every key in the constructor into a `public int` property (`$timeout`, `$maxRetries`, `$retryDelay`). Follow that exact pattern: add `public int $timestampTolerance;` assigned in the constructor via `$config->getInt('webhook.timestamp_tolerance')` (NO fallback parameter). Do NOT add a `timestampTolerance()` method — match the surrounding property style.
  - `/Users/markshust/Sites/marko/packages/webhook/config/webhook.php` (add `timestamp_tolerance` default, e.g. `300`).
  - `/Users/markshust/Sites/marko/packages/webhook/src/Exceptions/InvalidSignatureException.php` (existing, extends `MarkoException`; currently only `forRequest()` with a single message arg). Add a dedicated factory for the freshness failure (e.g. `staleTimestamp()` / `missingTimestamp()`) following the `message`/`context`/`suggestion` shape, OR reuse `forRequest()` — decide and document. A distinct factory is preferred so a replay is distinguishable from a bad signature.
  - Wiring: `webhook/module.php` currently binds only `WebhookDispatcherInterface`. `WebhookReceiver` is autowired on demand. If `WebhookReceiver` now needs the tolerance, it can inject `WebhookConfig` (autowirable, constructor takes `ConfigRepositoryInterface`) — confirm `WebhookConfig` resolves under autowiring, or read the tolerance inside `receive()`. Document the wiring decision.
- Patterns to follow:
  - Loud errors with `message`/`context`/`suggestion` named params and static factories.
  - Config default in `config/webhook.php`, surfaced via a `WebhookConfig` constructor-assigned public property (matches existing `$timeout` etc.) — NOT a getter method.
  - Timing-safe comparison via `hash_equals` (already used).
  - The signed message is `"$timestamp.$body"` (dot-delimited); HMAC = `'sha256=' . hash_hmac('sha256', "$timestamp.$body", $secret)`. The timestamp is an integer Unix epoch second sent in `X-Webhook-Timestamp`.
  - Tolerance check is symmetric: reject when `abs(time() - $timestamp) > $tolerance` (covers both stale-past and future-skew). The signature check (timing-safe) is independent of the freshness check; do BOTH.
- Existing-test impact (MUST update in this task — confirmed by reading the test files):
  - `tests/Receiving/WebhookReceiverTest.php` currently does `new WebhookReceiver(new WebhookVerifier())` and signs with body-only HMAC `hash_hmac('sha256', $body, $secret)` and no timestamp header. These tests WILL break under the new format. Update them to send `X-Webhook-Timestamp` and the new `"$timestamp.$body"` HMAC, and to construct the receiver with whatever new dependency it gains.
  - `tests/Receiving/WebhookVerifierTest.php` asserts the old `verify()` signature — update to the new one.
  - `tests/Sending/WebhookSignatureTest.php` asserts the old `sign($payload, $secret)` signature — update to the new one.
  - `tests/Sending/WebhookDispatcherTest.php` asserts the dispatched headers/signature — update to expect `X-Webhook-Timestamp` and the timestamped signature.
  - `tests/Sending/WebhookDeliveryServiceTest.php` — check whether it exercises `sign()`/`dispatch()` indirectly; update if it relies on the old body-only signature.
  - Run the full `packages/webhook/tests/` suite at the end — not just the new tests — to prove nothing else regressed.

## Requirements (Test Descriptions)
- [ ] `it accepts a freshly-signed request whose timestamp is within the tolerance window`
- [ ] `it rejects a request whose timestamp is older than the tolerance window`
- [ ] `it rejects a request whose timestamp is in the future beyond the tolerance window`
- [ ] `it rejects a request when the timestamp header is missing`
- [ ] `it accepts a request whose timestamp is exactly at the tolerance boundary`
- [ ] `it rejects a request when the timestamp is tampered with but the body signature was computed for a different timestamp`
- [ ] `it reads the tolerance from WebhookConfig (timestamp_tolerance) rather than a hardcoded value`
- [ ] `it round-trips a payload signed by WebhookSignature through WebhookReceiver successfully`
- [ ] `it keeps the existing WebhookReceiver JSON-parsing behavior for a valid signed-and-timestamped request`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
