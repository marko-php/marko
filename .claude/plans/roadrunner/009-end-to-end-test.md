# Task 009: End-to-End Integration Test

**Status**: pending
**Depends on**: 004, 006, 007, 008
**Retry count**: 0

## Description
Prove the whole thing actually works: a real Marko application served by a real RoadRunner process, with sessions, CSRF and auth intact across sequential requests from different identities.

## Context
- CI has no Docker or service containers (verified: `.github/workflows/ci.yml` has three jobs, none with `services:` or a container), so this test CANNOT gate every PR. Mark it `->group('integration-destructive')`, matching the existing precedent at `packages/mail-smtp/tests/Integration/StreamSocketIntegrationTest.php:26`. `composer test` excludes that group; `composer test:all` includes it.
- Skip gracefully with a clear message when the RoadRunner binary is absent, so a developer without it sees why rather than a confusing failure.
- The isolation assertions are the point of this task. Drive at least three sequential requests through one worker process — authenticated user A, then an **anonymous** request with no cookie, then user B — and assert no bleed. The anonymous request in the middle is the case that catches the confirmed `Session::$id` / `SessionGuard::$cachedUser` leak (fixed in #150 task 009); an A → B sequence alone would not.
- Reuse the fixture application from task 004a rather than building a second one. This test differs from 004a only in that requests arrive over real HTTP through a real `rr` process.
- **PSR-7 containment moved to task 001.** It is a static source scan with no dependency on anything here, and writing it first guards every task in the plan instead of only the last. Do not duplicate it.

### Without a CI step this test never runs anywhere but a laptop

`.github/workflows/nightly.yml` runs `composer test:all` on `ubuntu-latest` with no RoadRunner binary installed, so this test would **always skip** — the security-critical isolation assertions would never execute in CI at all. That defeats the plan's own stated mitigation ("runs locally and nightly").

Add a step to `nightly.yml` that installs the RoadRunner binary before `composer test:all` (`vendor/bin/rr get-binary` from `spiral/roadrunner-cli`, or download the release archive directly), and add `spiral/roadrunner-cli` to the root `require-dev` if the former. Then assert in `tests/CiWorkflowTest.php` style that the nightly workflow installs it, so a future edit cannot silently drop the step and turn the whole suite back into a skip.

## Requirements (Test Descriptions)
- [ ] `it serves a successful http response through a real roadrunner process`
- [ ] `it preserves a session across two requests from the same client`
- [ ] `it does not leak session state between two different clients`
- [ ] `it does not leak the authenticated user into an anonymous request`
- [ ] `it does not leak the authenticated user between two different clients`
- [ ] `it sets a session cookie on the first request and not on the second`
- [ ] `it passes a csrf protected form submission`
- [ ] `it returns a five hundred and keeps serving after a request throws`
- [ ] `it skips with a clear message when the roadrunner binary is unavailable`
- [ ] `it installs the roadrunner binary in the nightly workflow`

## Acceptance Criteria
- All requirements have passing tests
- End-to-end tests are in the `integration-destructive` group
- `composer test` stays green without a RoadRunner binary present
- `nightly.yml` installs the RoadRunner binary so `composer test:all` actually exercises this suite
- Code follows code standards

## Implementation Notes
