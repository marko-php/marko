# Plan: Tier 5 — Low-Severity Hardening

## Created
2026-06-10

## Status
ready

## Objective
Harden a set of low-severity security and correctness gaps across nine packages (webhook, session-file, sse, encryption-openssl, log/log-file, core, routing, testing, layout, cache-file) without changing public contracts. Each fix is small, isolated to a single file-cluster, and verified by behavioral TDD tests.

## Related Issues
none

## Discovery Notes
- **New work.** No existing plan in `.claude/plans/` covers these specific hardening items. Adjacent plans (`sse-package`, `session-package`, `encryption`, `core-package`, `routing-package`, `cache-package`, `testing-package`, `layout-component-discovery`) define the packages themselves; this plan only touches already-built code.
- **Tasks 013–024 are gap-audit + dropped-from-consolidation follow-ups now folded into this tier.** They were surfaced by a later low-severity audit pass (and items deferred out of an earlier consolidation), and are added here without renumbering or touching tasks 001–012. Each was independently re-verified by reading its cited source before being written; path/line drift was corrected against the actual files.
- **Source-read drift corrections for the added tasks (verification notes):**
  - **018:** The SQL generators live at `packages/database-{mysql,pgsql}/src/Sql/*Generator.php`, NOT `src/Schema/`. Confirmed maps: MySQL `TYPE_MAP` is missing `uuid`/`enum`; PgSQL `TYPE_MAP` is missing `tinyint`/`bool`/`blob` aliases; `decimal` is `DECIMAL(10,2)` on mysql vs bare `DECIMAL` on pgsql.
  - **019:** The MySQL connection does NOT have a `bindValues()` method; it calls `$statement->execute($this->prepareBindings($bindings))`, where `prepareBindings()` only JSON-encodes array values — everything else binds as `PARAM_STR`. The pgsql sibling's `bindValues()` (`PgSqlConnection.php` ~187-213) selects explicit `PARAM_BOOL`/`PARAM_NULL`/`PARAM_INT`/`PARAM_STR` and IS the parity target.
  - **022:** PACKAGE drift — `AdminAuthMiddleware` and `PermissionRegistry`/`PermissionRegistryInterface` live in `packages/admin-auth/`, NOT `packages/admin-api/`. The middleware does honor wildcards via `PermissionRegistry::matches()`; `SectionController` (admin-api) uses bare `$user->hasPermission()` and currently injects only `AdminSectionRegistryInterface` + `GuardInterface` (so the fix must inject `PermissionRegistryInterface`).
  - **023:** Confirmed — `Job::$attempts` is `public private(set) int = 0` with only `incrementAttempts()` and no reset; `JobInterface` exposes `attempts` as a get-only hook, so the fix adds a `resetAttempts()` to `Job` and `JobInterface`. `Worker` gates real retries on `$job->attempts < $job->maxAttempts`, confirming a non-reset retry re-fails immediately.
  - **024:** Confirmed — `DebugbarStorage::all()` calls the full-decode `get($id)` per file; the default `masked` list (`config/debugbar.php`) uses `*`→`.+` matching, so `*.password` cannot mask a TOP-LEVEL `password`, and `dsn` is absent entirely.
  - **016:** `LspProtocol::handleMessage()` ALREADY emits `-32700` for un-decodable JSON bodies; the gap is purely that a header-level malformed frame (`Content-Length: 0`) collapses to the same `null` as EOF in `serve()`, so the fix is the EOF-vs-malformed distinction at the protocol boundary.
  - **014:** `DocsException::searchFailed()` factory already exists — reuse it, no new factory needed.
- **All cited findings reproduced in source; none were skipped.** The only adjustments were path/line/method-name drift (above), not absent bugs.
- **STALE / CORRECTED — Tier 3 DOES interact with `Request::path()`.** The original note ("`tier3-medium-fixes/` is empty; Task 008 owns `path()` outright") was written before Tier 1–4 merged and is now wrong. Tier 3 (merged) added per-parameter percent-decoding in `RouteMatcher::extractParameters()` (`rawurldecode($matches[$name])`, `RouteMatcher.php` line 64) plus a test (`RouteRegexEscapingTest.php`: "rawurldecodes a matched parameter value exactly once") that pins decode-once semantics. Since `Router::match(..., $request->path())` feeds `path()` straight into the matcher, adding `rawurldecode` to `path()` would double-decode parameters and turn an encoded `%2F` into a structural `/`. **Task 008 has been scoped down to the Content-Type/Content-Length `header()` CGI-key fallback ONLY; the `path()` decode is removed.**
- **Existing conventions confirmed by source reads:**
  - All target packages already use `MarkoException`-style exceptions with `message`/`context`/`suggestion` named params and static factory methods (`InvalidSignatureException`, `SseException`, `LayoutException`→`AmbiguousSortOrderException`, `BindingException`, `LogWriteException`, `DecryptionException`/`EncryptionException`). New factories must follow this shape.
  - `EncryptionException`/`DecryptionException` extend a local base (NOT `MarkoException`) but share the same constructor signature.
  - Config defaults belong in `config/*.php`; getters on `*Config` classes call `ConfigRepositoryInterface::getInt/getString/getBool` with NO fallback. New config keys (webhook timestamp tolerance, SSE subscription heartbeat, log escaping flag) must be added to the relevant `config/*.php` AND exposed via a `*Config` getter.
  - `Request::fromGlobals()` already reads `CONTENT_TYPE` then `HTTP_CONTENT_TYPE`. The actual bug is in the instance method `Request::header()`, which only maps `HTTP_*` server keys — so `header('Content-Type')` misses the CGI `CONTENT_TYPE`/`CONTENT_LENGTH` keys. That is the surface Task 008 fixes.
  - Real `Session::has()` uses `array_key_exists` (so a stored `null` reports present) and asserts started; `FakeSession::has()` uses `isset` (a stored `null` reports absent). Task 010 aligns the null semantics only.
  - `pubsub`'s `Subscription` is an `IteratorAggregate` interface; `SseStream::iterateSubscription()` only checks timeout inside the `foreach` body, so an idle subscription that yields nothing blocks forever and never emits a heartbeat. Task 005 adds heartbeat + idle-timeout to the subscription path.

## Scope
### In Scope
- F1 Webhook replay protection: signed-timestamp freshness window with configurable tolerance.
- F2 Session-file permissions + unchecked write returns: chmod 0600 files / 0700 dir, check `fwrite`/`ftruncate`.
- F3 SSE `event`/`id` CR/LF sanitization + subscription-path heartbeat/idle-timeout.
- F4 OpenSSL AEAD enforcement, false iv-length handling, payload field-type validation.
- F5 Log line-injection: CR/LF escaping option for the line formatter (default-safe).
- F6 misc correctness: container cycle detection, manifest `php*`-vendor filter, request Content-Type/Content-Length CGI-key header fallback (path decode dropped — collides with Tier 3 RouteMatcher), FakeSession null semantics, layout deterministic ambiguity detection, cache-file tmp cleanup / clear glob / mkdir race.
- F7 tooling/devx hardening (added tasks 013–017): codeindexer cache deserialize safety + corruption rebuild; docs-fts MATCH error → `DocsException`; mcp read-only DB guard against stacked statements; lsp resilience to a malformed frame; Translator placeholder-ordering correctness.
- F8 database sibling parity (added tasks 018–020): SQL generator type-map parity (mysql/pgsql), MySQL connection explicit PDO param binding, valid empty `whereIn` on both builders (`whereNotIn` removed from scope — no such method exists in the codebase).
- F9 media/admin/queue/debugbar correctness (added tasks 021–024): GD format+alpha preservation and checked encodes; admin-api section visibility wildcard-awareness + `show()` filter parity; queue retry attempt reset; debugbar lazy `all()` + default-mask completeness.

### Out of Scope
- Any change to public interface signatures (`WebhookReceiverInterface`, `SessionHandlerInterface`, `EncryptorInterface`, `LoggerInterface`, `ContainerInterface`, `SessionInterface`).
- Sending-side webhook signature format (only the receiving verifier gains a timestamp check; sender changes are noted as a follow-up only where strictly required to make the receiver testable).
- Redis/database cache or session drivers (only `cache-file` and `session-file`).
- New SSE transports or pubsub backends.
- README/docs updates (handled by the doc-updater pipeline agent).

## Success Criteria
- [ ] Inbound webhooks reject stale and future-skewed timestamps within a configurable tolerance, and accept fresh ones.
- [ ] Session files are created with 0600 permissions and partial/failed writes surface a loud error.
- [ ] SSE `event`/`id` containing CR/LF are rejected loudly; idle subscriptions respect timeout and emit heartbeats.
- [ ] OpenSSL encryptor rejects non-AEAD ciphers at construction, handles false iv-length, and converts malformed payloads to `DecryptionException` (never a `TypeError`).
- [ ] Log messages containing newlines cannot forge a second log line when escaping is enabled (default).
- [ ] Mutual constructor dependency cycles throw a loud `CircularDependencyException` instead of exhausting the stack.
- [ ] `phpunit/phpunit` and other `php*`-vendor packages survive manifest filtering; `php`, `php-64bit`, `ext-*`, `lib-*` are still dropped.
- [ ] `Request::header('Content-Type')` reads the CGI `CONTENT_TYPE` key (the `path()` percent-decode was dropped — it would double-decode against Tier 3's RouteMatcher).
- [ ] `FakeSession::has()` reports a stored `null` as present, matching production `Session::has()`.
- [ ] Layout ambiguity detection finds an ambiguous pair deterministically even with 17+ components.
- [ ] cache-file leaves no orphan `.tmp` files on rename failure, `clear()` removes tmp files too, and concurrent directory creation does not error.
- [ ] codeindexer treats a corrupt cache as a rebuild trigger (never a silently-empty index) and restricts `unserialize` to a class allowlist.
- [ ] docs-fts converts a malformed FTS5 MATCH into `DocsException` (never a raw `PDOException`).
- [ ] mcp read-only DB tool rejects stacked statements (`SELECT 1; DELETE ...`) while still allowing plain selects and opt-in writes.
- [ ] lsp server responds to a malformed frame with a `-32700` parse error and keeps serving; only true EOF ends the loop.
- [ ] Translator resolves `:attribute` correctly when `:attr` also exists and never re-replaces a placeholder inside a replacement value.
- [ ] Each shared abstract column type (`uuid`, `enum`, `bool`, `tinyint`, `blob`, `decimal`) generates valid DDL on BOTH mysql and pgsql generators, with consistent decimal precision.
- [ ] MySQL connection binds `false`/`true`/`null`/int with correct PDO types (parity with pgsql), not coerced to strings.
- [ ] `whereIn(col, [])` generates valid SQL (no-match `1 = 0`) on both builders — never `IN ()`. (`whereNotIn` is out of scope: no such method exists.)
- [ ] GD resize/crop preserve the source format and transparency, and surface a loud error on encode failure.
- [ ] admin-api section visibility honors wildcard permissions, and `show()` enforces the same filter as `index()`.
- [ ] queue RetryCommand resets a retried job's attempts so the worker performs real retries.
- [ ] debugbar `all()` lists summaries without fully decoding every dataset, and the default mask redacts common secret-named keys (`password`/`secret`/`key`/`token`/`dsn`) at top level and nested.
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Webhook inbound replay protection (timestamp freshness window) | - | pending |
| 002 | Session-file restrictive permissions + checked writes | - | pending |
| 003 | SSE `event`/`id` CR/LF sanitization | - | pending |
| 004 | OpenSSL AEAD enforcement + payload type validation | - | pending |
| 005 | SSE subscription heartbeat + idle timeout | - | pending |
| 006 | Log line-injection CR/LF escaping (line formatter) | - | pending |
| 007 | Core container circular-dependency detection | - | pending |
| 008 | Routing Request Content-Type/Content-Length header CGI-key fallback (path decode dropped — Tier 3 collision) | - | pending |
| 009 | Core manifest `php*`-vendor dependency filter fix | - | pending |
| 010 | FakeSession null-key `has()` parity | - | pending |
| 011 | Layout deterministic ambiguous-sort-order detection | - | pending |
| 012 | cache-file tmp cleanup, clear glob, mkdir race | - | pending |
| 013 | codeindexer cache unserialize hardening (allowed_classes + non-array load failure) | - | pending |
| 014 | docs-fts malformed MATCH wraps PDOException in DocsException | - | pending |
| 015 | mcp read-only DB guard rejects stacked statements | - | pending |
| 016 | lsp server resilience to a malformed frame (parse error, keep serving) | - | pending |
| 017 | Translator placeholder replacement ordering (single-pass strtr) | - | pending |
| 018 | SQL generator type-map parity across mysql/pgsql | - | pending |
| 019 | MySQL connection binds explicit PDO param types (parity with pgsql) | - | pending |
| 020 | query builder empty `whereIn` → valid no-match (`1 = 0`); `whereNotIn` out of scope (no such method) | cross-tier rebase | pending |
| 021 | GD preserve source format + alpha; check encode returns | - | pending |
| 022 | admin-api section visibility wildcard-aware + show() filter parity | - | pending |
| 023 | queue RetryCommand resets attempts before re-queue | Tier-2 coordination | pending |
| 024 | debugbar lazy `all()` + default-mask completeness | - | pending |

The original twelve tasks (001–012) are independent (no shared files). Tasks 003 and 005 both touch the `sse` package but different files (Task 003: `SseEvent.php` + `SseException.php`; Task 005: `SseStream.php` only). They may run in parallel, but note a LOGICAL coupling: `SseStream::iterateSubscription()` constructs `new SseEvent(data:, event:)`, and Task 003 adds CRLF validation to the `SseEvent` constructor. If Task 005 lands first, its message fixtures must use channel/payload values WITHOUT CRLF so they remain valid once Task 003's constructor guard exists. Whichever task finishes last MUST re-run the full `packages/sse/tests/` suite to catch interaction regressions.

Tasks 013–024 (gap-audit + dropped-from-consolidation follow-ups) are likewise independent and parallel. NOTE: Tier 1–4 are ALREADY MERGED on this branch, so the cross-tier items below are now "build against current merged source," not "rebase onto a pending tier":
- **Task 020** shares `MySqlQueryBuilder.php` / `PgSqlQueryBuilder.php` with the already-merged Tier 1/2/3 query-builder changes. The `IN (%s)` compile loop is currently at mysql lines 950-957 / pgsql lines 958-967 — re-confirm by searching for the `sprintf('%s IN (%s)', ...)` before editing in case a same-wave task shifts it, and re-run both builders' suites. Scope is empty-`whereIn` ONLY (`whereNotIn` does not exist).
- **Task 023** consumes the already-merged Tier 1 `JobEnvelope` HMAC seam and Tier 2 attempt model in `RetryCommand` / `Job`. It adds `resetAttempts()` to `Job`/`JobInterface` and inserts the reset between `unserialize(verifyAndUnwrap(...))` and `queue->push(...)` — it must NOT remove the `verifyAndUnwrap` envelope call. (See its Context + Implementation Notes.)
- Tasks 018, 019, 020 all touch the `database-mysql`/`database-pgsql` sibling pair but DIFFERENT files (018: `src/Sql/*Generator.php`; 019: `src/Connection/*Connection.php`; 020: `src/Query/*QueryBuilder.php`), so they are mutually parallel.

## Architecture Notes
- **Exceptions:** Reuse each package's existing base exception. New factory methods follow `message`/`context`/`suggestion` named-parameter convention. New exception classes:
  - `Marko\Webhook\Exceptions\ReplayException` (or new factory on `InvalidSignatureException`) extending `MarkoException`.
  - `Marko\Sse\Exceptions\SseException::invalidField()` (new static factory) for CR/LF in `event`/`id`.
  - `Marko\Encryption\Exceptions\EncryptionException` — new factories `nonAeadCipher()` and `invalidCipher()`; `DecryptionException::invalidPayload()` already exists and is reused for bad field types.
  - `Marko\Log\Exceptions\LogWriteException` already exists — reuse for partial-write surfacing if log work needs it (it does not; F5 is formatter-only).
  - `Marko\Session\File\Exceptions\SessionWriteException` (new, extending `MarkoException`) for partial/failed session writes.
  - `Marko\Core\Exceptions\CircularDependencyException` extending `MarkoException` (and implementing `ContainerExceptionInterface` like `BindingException`).
- **Config:** New keys and `*Config` accessors. NOTE the two config-class styles in play:
  - `WebhookConfig` reads keys in its CONSTRUCTOR into `public int` properties (no getter methods). So `webhook.timestamp_tolerance` is exposed as `public int $timestampTolerance;` assigned in the constructor — NOT a `timestampTolerance()` getter. (Task 001.)
  - `LogConfig` uses lazy GETTER methods. So `log.escape_newlines` is exposed as `LogConfig::escapeNewlines()` calling `getBool('log.escape_newlines')`. (Task 006.)
  - `EncryptionConfig` uses lazy getters (`key()`, `cipher()`); no new key needed — Task 004 caches `cipher()` once at construction in a private property to enable construction-time validation.
  - `sse` has NO config file; Task 005 keeps the existing `SseStream` constructor defaults (`heartbeatInterval`, `timeout`, `pollInterval`) and only fixes the subscription-loop logic — NO new config file required.
- **Timestamp signing (F1):** The receiver verifies an `X-Webhook-Timestamp` header against `time()` within `±tolerance` (symmetric: `abs(time() - $timestamp) > $tolerance` rejects both stale-past and future-skew), AND folds the timestamp into the HMAC input so the timestamp itself is tamper-evident (signing `"$timestamp.$body"`). This changes the wire format AND the in-package signatures: `WebhookVerifier::verify()` gains a timestamp param + tolerance; `WebhookSignature::sign()` incorporates the timestamp; `WebhookReceiver::receive()` reads `X-Webhook-Timestamp` and sources the tolerance from `WebhookConfig`. All three plus the existing `WebhookReceiverTest`/`WebhookVerifierTest` change in lockstep in Task 001 (the existing tests sign body-only and will otherwise break). Tests cover freshness window (stale, future, boundary), tamper detection, missing-timestamp rejection, and a full sender→receiver round-trip.
- **AEAD detection (F4):** The current code re-reads `$this->config->cipher()` inside `encrypt()`/`decrypt()`; Task 004 fetches and validates the cipher ONCE in the constructor, storing it in a private readonly property, so a non-AEAD/unknown cipher fails at construction (not first-encrypt). Validate the cipher is in `openssl_get_cipher_methods()` (lowercase-normalized) AND ends in an AEAD mode (`-gcm`/`-ccm`). Handle `openssl_cipher_iv_length()` returning `false` at construction. Replace `isset()` payload checks with explicit `is_string()` checks on `iv`/`value`/`tag`. Add the missing `@throws RandomException` (or catch-convert) to `encrypt()` since `random_bytes()` is called.
- **Container cycle (F6):** Maintain a private `array<string,bool> $resolving` set keyed by class id; mark on entry to constructor-dependency resolution, clear on exit (including on exception via `finally`), throw `CircularDependencyException` when an id is re-entered. Build the dependency chain string for the error message.
- **Layout ambiguity (F6):** Move ambiguity detection OUT of the `usort` comparator. After grouping by `sortOrder`, deterministically scan each group: if a group has ≥2 components that are all unresolved (`before === null && after === null`), throw `AmbiguousSortOrderException`. Then sort with a total-order comparator that never throws.
- **cache-file (F6):** On `rename()` failure, `@unlink($tempPath)` and return false. `clear()` globs both `*.cache` and `*.tmp.*`. `ensureDirectoryExists()` uses `@mkdir(..., recursive: true)` then re-checks `is_dir()` so a concurrent creator that won the race is tolerated; only a still-missing directory is an error.

## Risks & Mitigations
- **F1 changes the signed-message format AND two in-package signatures** → sender and receiver could disagree, and the existing `WebhookReceiverTest`/`WebhookVerifierTest` (which sign body-only with no timestamp) WILL break. Mitigation: Task 001 updates `WebhookVerifier::verify()` (new timestamp param + tolerance), `WebhookSignature::sign()` (incorporate timestamp), `WebhookReceiver::receive()` (read `X-Webhook-Timestamp`, source the tolerance from `WebhookConfig`), AND the existing tests, all in lockstep; a round-trip test proves a freshly-signed payload verifies, and clock-skew tests cover stale-past, future-skew, and the tolerance boundary.
- **F3 subscription heartbeat cannot use a plain `foreach`** → a `foreach` over the backend generator blocks until a message arrives, so the loop can never tick while idle. Mitigation: Task 005 iterates the subscription manually (advancing the generator) and treats no-message/`null` as an idle tick on which timeout + heartbeat are evaluated; tests use a fake `Subscription` yielding a bounded deterministic sequence (no real clock/socket).
- **F5 default-on escaping could mangle intentionally multi-line human logs** → Mitigation: escaping is config-driven (`log.escape_newlines`, default `true`) and only collapses CR/LF in the interpolated `{message}`/`{context}` segments to literal `\n`/`\r` escape sequences. WIRING NOTE: the flag is threaded through the `log/module.php` closure that builds `LineFormatter` (NOT `FileLoggerFactory`, which only receives an already-built formatter). A test covers a legitimately multi-line message rendering on a single physical line when enabled, and an end-in-newline message still producing exactly one trailing newline.
- **F4 cipher validation could break existing configs** → default cipher is already `aes-256-gcm` (AEAD), so the default path is unaffected; only explicitly-misconfigured non-AEAD ciphers now fail loudly at construction (desired).
- **Container `$resolving` set on a long-lived container** → must be cleared in a `finally` so a thrown `BindingException` mid-resolution does not poison later resolutions. Test covers resolve-after-failure.
- **Session/cache permission tests are filesystem-dependent** → use `sys_get_temp_dir()` per existing `FileSessionHandlerTest` helpers; assert via `fileperms(...) & 0777`. Skip-safe on platforms where chmod is a no-op is acceptable but the assertion targets POSIX (CI is ubuntu).
