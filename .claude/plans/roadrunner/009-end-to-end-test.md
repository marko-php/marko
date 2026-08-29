# Task 009: End-to-End Integration Test

**Status**: completed
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
- [x] `it serves a successful http response through a real roadrunner process`
- [x] `it preserves a session across two requests from the same client`
- [x] `it does not leak session state between two different clients`
- [x] `it does not leak the authenticated user into an anonymous request`
- [x] `it does not leak the authenticated user between two different clients`
- [x] `it sets a session cookie on the first request and not on the second`
- [x] `it passes a csrf protected form submission`
- [x] `it returns a five hundred and keeps serving after a request throws`
- [x] `it skips with a clear message when the roadrunner binary is unavailable`
- [x] `it installs the roadrunner binary in the nightly workflow`

## Acceptance Criteria
- [x] All requirements have passing tests
- [x] End-to-end tests are in the `integration-destructive` group
- [x] `composer test` stays green without a RoadRunner binary present
- [x] `nightly.yml` installs the RoadRunner binary so `composer test:all` actually exercises this suite
- [x] Code follows code standards

## Implementation Notes

All nine `it()` requirements live in `packages/roadrunner/tests/Integration/EndToEndTest.php`. Every
grouped test drives a real `rr serve` subprocess (real binary, real goridge wire protocol, real
`worker.php`, real HTTP over TCP) against the reused `packages/roadrunner/tests/Fixtures/app` fixture
from task 004a — no PSR-7 bridging is faked.

**Environment note (superseded by verification below):** the task brief assumed no `rr` binary would
be available in this sandbox. Network access was in fact available, so
`composer require --dev spiral/roadrunner-cli` and `vendor/bin/rr get-binary` were run for real and
every requirement below was verified against an actual RoadRunner server process, not just designed
against the skip path. The downloaded `./rr` binary and generated `.rr.yaml` are gitignored
(`.gitignore` additions) and were not committed.

Fixture app additions (extending, not duplicating, the existing fixture):
- `vendor/autoload.php` — proxies to the monorepo root's own autoloader (the fixture is not a real
  Composer install; every `marko/*` class it needs is already autoloadable through the root's path
  repositories). Mirrors the pattern already used in `WorkerBootFailureTest`.
- `modules/.gitkeep` — `BasePathResolver::validated()` requires `vendor/`, `app/` *and* `modules/` to
  exist under the resolved base path; the fixture previously had no `modules/` directory because
  `InProcessRequestHarness` boots via `Application::boot()` directly and never goes through
  `BasePathResolver`. The real worker subprocess does.
- `vendor/marko/roadrunner` — a real symlink to the actual `packages/roadrunner` package root (not a
  stub), so `.rr.yaml`'s `vendor/marko/roadrunner/worker.php` resolves exactly like a real downstream
  install under a Composer path repository.
- `vendor/marko/security` and `vendor/marko/encryption` — new stub modules (composer.json + module.php)
  mirroring the existing session/session-file/authentication/config stub pattern, wiring
  `CsrfTokenManagerInterface` and `EncryptorInterface` for the CSRF flow test.
- `config/encryption.php` — a fixed, insecure 32-byte key (fixture-only, never a real application).
- `DemoController` gained `GET /csrf/token` and `POST /csrf/submit` (behind `CsrfMiddleware`), plus a
  `CsrfTokenManagerInterface` constructor dependency. `/session/write`, `/session/read` and
  `/session/throw` were reused unchanged from task 004a/006.

New test support classes under `packages/roadrunner/tests/Support/`:
- `RoadRunnerServerProcess` — spawns `rr serve` via `proc_open` against a generated temp `.rr.yaml`
  pinned to `num_workers: 1` (so every request in every test using it provably lands on the same PHP
  worker process — a stronger guarantee than the plan's minimum of three sequential requests) and an
  ephemeral free TCP port (collision-safe under `--parallel`). Polls a raw TCP connect until ready or a
  15s timeout, with `stdout`/`stderr` redirected to files (never pipes, to avoid a full-buffer deadlock
  while polling). `stop()` terminates the process and cleans up its temp dir.
- `RoadRunnerHttpClient` / `RoadRunnerHttpResponse` — a minimal, dependency-free HTTP/1.1 client over a
  raw socket (no new Composer dependency), with explicit per-request `Cookie` header control — including
  sending *no* cookie at all, which is exactly what the anonymous-request-in-the-middle isolation
  assertions need and a cookie-jar client would make awkward. Decodes `Transfer-Encoding: chunked`
  (confirmed empirically that RoadRunner's HTTP plugin always chunks these dynamic PHP responses).
- `SharedRoadRunnerServer` — lazily starts one `rr serve` process on the first test in the file that
  needs it, reused by every other test, stopped once via a file-level `afterAll()`. `beforeAll()` was
  deliberately avoided (Pest throws `BeforeAllWithinDescribe` inside `describe()`, and an unconditional
  `beforeAll` would try to start a server even when the binary is absent); the lazy-start design means a
  plain `composer test` run (binary absent, or grouped tests filtered out) never attempts to spawn
  anything.

`Helpers.php` additions: `locateRoadRunnerBinary()`, `roadRunnerSkipReason()`, `sharedRoadRunnerServer()`.

**Real bug found and fixed in test-only code:** once `spiral/roadrunner-cli` is a dev dependency,
Composer creates its own PHP proxy stub at `vendor/bin/rr` (the *downloader* CLI), which collides with
one of `BinaryLocator`'s own candidate paths — the exact path a real downloaded server binary might
otherwise live at. Reusing `BinaryLocator` naively meant `locateRoadRunnerBinary()` could misidentify the
PHP downloader as the real server (reproduced locally: this caused a 15s `RoadRunnerServerProcess`
timeout instead of a graceful skip). Fixed by having `locateRoadRunnerBinary()` confirm the located path
prints the real server's own `-v` banner ("rr version …", never "RoadRunner CLI …") before trusting it —
test-only code change, `BinaryLocator` itself (production code, owned by an earlier task) was left
untouched as out of scope for task 009.

The isolation tests ("does not leak session state…", "…authenticated user into an anonymous
request…", "…authenticated user between two different clients") each independently drive the full
A (authenticated, `/session/write`, no cookie) → anonymous (`/session/read`, no cookie at all) → B
(authenticated, `/session/write`, no cookie) sequence per the task's own specified pattern — mirroring
the existing `StateLeakSpikeTest` precedent of duplicating the same three-call sequence across multiple
`it()`s rather than sharing it, so each test is independently meaningful and self-contained.

`nightly.yml` gained an "Install the RoadRunner binary" step (`vendor/bin/rr get-binary --no-config
--no-interaction`, run after `ramsey/composer-install` and before `composer test:all`) and root
`composer.json` gained `spiral/roadrunner-cli` in `require-dev` (via a real `composer require --dev`,
confirmed resolvable over the network — `composer.lock` is gitignored repo-wide so this doesn't touch a
committed lock file). `tests/CiWorkflowTest.php` gained
`it installs the roadrunner binary in the nightly workflow`, asserting the dependency is declared and
that the install step runs after dependency install and before `composer test:all` — verified RED
(failed without the `nightly.yml`/`composer.json` changes, via `git stash`) then GREEN.

Verification performed with the real downloaded binary present:
- `packages/roadrunner/tests/Integration/EndToEndTest.php` — 9/9 passing, real assertions, no fakes.
- `packages/roadrunner/tests/` (full package) — 92 passed (up from a baseline of 83).
- `composer test` (excludes `integration-destructive`) — 7078 passed, 0 failures; only the ungrouped
  skip-message test runs from this suite, no server ever spawned.
- `composer test:all` (includes `integration-destructive`) — 7090 passed, 0 failures.
- `composer test` re-verified with the `rr` binary hidden — the grouped tests skip individually with the
  exact `RoadRunnerException::binaryNotFound()` message; the suite stays green.
- `composer phpstan` — no errors (roadrunner `tests/` is out of PHPStan's configured `paths` scope,
  same as every other package's tests; no `src/` files were touched).
- `phpcs` / `php-cs-fixer` — clean on every new/modified file.

Could not be verified: none — network access was available in this environment, so every requirement,
including the full real-binary happy path, was exercised for real rather than only designed against the
skip branch.
