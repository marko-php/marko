# Plan: Tier 3 Medium-Severity Security & Correctness Fixes

## Created
2026-06-10

## Status
completed

## Objective
Fix twenty medium-severity security and correctness defects. The original twelve span CORS, media processing, API tokens, admin CSRF, routing, validation, scheduling, queued jobs, session storage, page-cache, and the S3 filesystem driver. A gap-audit / dropped-from-consolidation follow-up adds eight more (tasks 013–020) across docs-markdown, devai process running, the amphp listener, both pubsub drivers, the MySQL and pgsql query builders, the Inertia integration, and the simple error handler. Each fix is a focused, test-first change to existing packages — no new packages.

## Related Issues
none

## Discovery Notes

All twelve findings touch packages that already exist and have completed plans (`cors`, `media`/`media-imagick`, `authentication-token`, `admin-panel`/`admin-api`/`security`, `routing-package`, `validation-package`, `scheduler`, `session`/`session-database`, `page-cache-package`, `filesystem-s3`). These are bug-fix follow-ups, NOT net-new package work. Overlapping plan directories were reviewed (`scheduler`, `session-database`, `page-cache-package`, `filesystem-s3`, `security`, `validation-package`, `routing-package`, `notification`, `auth-package`, `admin-system`, `cache`, `authorization`); none of them contain in-flight tasks that conflict with these fixes — they describe the already-shipped feature set that these tasks now harden.

Confirmed source facts (read at planning time):
- **F1** `CorsMiddleware` (cors) reflects `Origin` into `Access-Control-Allow-Origin` (line 30, 47) and unconditionally adds `Access-Control-Allow-Credentials: true` when `supportsCredentials()` is true (lines 49-51), with no `Vary: Origin`. `isOriginAllowed()` returns true for any origin when `*` is in the allowlist (lines 66-68). `CorsConfig` getters throw `ConfigNotFoundException`.
- **F2** `MediaManager::upload()` trusts caller-supplied `UploadedFile->mimeType` (lines 34-35, 49). `ImagickImageProcessor` does `new Imagick($path)` and writes using `getImageFormat()` with no allowlist (resize ~38/46-48, crop ~73/76-78, plus convert/optimize methods further down). `media` config has `allowed_mime_types` and `allowed_extensions`. `ImagickProcessingException` currently has no factory methods (bare `extends MarkoException`).
- **F3** `PersonalAccessToken` already has a nullable `expiresAt` column (line 37) and `ExpiredTokenException::forToken()` already exists. `TokenManager::createToken()` (lines 20-42) never sets `expiresAt`. `TokenGuard::resolveTokenEntity()` (lines 52-69) does the timing-safe `hash('sha256', ...)` lookup but never checks expiry. `NewAccessToken` holds `accessToken` + `plainTextToken`.
- **F4** `security` already ships `CsrfMiddleware` (validates non-safe methods via `CsrfTokenManagerInterface::validate()` + `_token`/`X-CSRF-TOKEN`), `CsrfTokenManager` (session+encryptor; token stored under `_csrf_token`), and binds them in `module.php`. `admin-panel/LoginController` POSTs (`authenticate`, `logout`) are the only framework-shipped state-changing admin routes. **Review correction: `admin-api`'s `MeController` and `SectionController` define ONLY `#[Get]` routes** (`/admin/api/v1/me`, `/admin/api/v1/sections`, `/admin/api/v1/sections/{id}`) — `CsrfMiddleware` is a no-op on GET, so admin-api gets no CSRF wiring (would be pseudo-functionality). F4 is scoped to admin-panel only. The `#[Middleware]` attribute targets `TARGET_CLASS | TARGET_METHOD` and accepts a single class or array — apply at method level on `authenticate`/`logout` so the GET `showLoginForm` stays reachable. admin-panel does not currently require `marko/security`. The login `.latte` template lives in sibling `admin-panel-latte`/`admin-panel-twig` packages, not admin-panel itself; the controller passes `loginUrl` into the view, so a CSRF token must be passed the same way.
- **F5** `Router::resolveParameters()` (lines 100-111) applies `castToType()` only to route params; POST (`$postValue`) and query (`$queryValue`) are injected raw, and a missing required param is injected as `null` (line 110), causing a `TypeError` on typed scalar params. `castToType()` (lines 117-131) handles `int|float|bool` via cast.
- **F6** `RouteDefinition::buildRegex()` (lines 39-45) substitutes `{param}` then wraps in `#^...$#` with no `preg_quote` of literal text, so `.` matches any char and `#` breaks the delimiter. `Request::path()` does not url-decode.
- **F7** `Min`/`Max`/`Between` check `is_string` (length) BEFORE `is_numeric`, so an HTTP string like `"25"` is length-checked. `RuleInterface::passes(field, value, data)` receives the full `$data` array (cross-field aware). `Integer`/`Numeric` rules already exist. `In`/`NotIn` use strict `in_array(..., true)`, so `"1"` never matches an allowed `1`.
- **F8** `CronExpression::matchField()` (lines 29-60): step `*/n` only handles a leading `*/`, never `a-b/n`; lists and ranges use `intval` so `1-5,10` and `0-58/2` mis-parse; DOW `7` (Sunday) never matches (PHP `w` gives 0-6); `matches()` ANDs all five fields, so a restricted DOM+DOW are ANDed (standard cron ORs them); malformed expressions return false silently (no loud error). The scheduler package has no exceptions directory yet.
- **F9** `Job::serialize()` is `serialize($this)`. `Worker::work()` does `pop()` → `handle()` directly (no resolver). `DispatchWebhookJob` holds a `WebhookDispatcherInterface`, `WebhookDeliveryService`, `ConfigRepositoryInterface`, and `QueueInterface` (Guzzle client + PDO behind them) — unserializable. `SendNotificationJob` holds a `NotificationSender` (channels → PDO/mailer). `AsyncObserverJob::handle(?callable $resolver = null)` is the existing container-resolution pattern to mirror.
- **F10** `DatabaseSessionHandler::write()` (lines 43-58) does `DELETE` then `INSERT` (non-atomic). `Session::save()` (lines 222-230) early-returns silently when `!$this->started`; after `session_write_close()`, `$this->started` is NOT reset, so later writes are accepted into `$this->data` but never persisted — silent data loss.
- **F11** `CacheKey::fromRequest()` builds the query with `http_build_query($queryArray)` (line 25, default RFC1738 `+` for spaces). `CacheKey::normalizeQuery()` pre-encodes `+` to `%2B` then uses `PHP_QUERY_RFC3986` (line 35-39, `%20` for spaces). The two produce different strings, so the `hash()` differs and purge-by-URL misses.
- **F12** `S3Filesystem::listDirectory()` (lines 409-430) builds `$prefix` with a trailing `/` and root special-case, and ignores `IsTruncated`/`NextContinuationToken` (single `listObjectsV2`). `deleteDirectory()` (lines 510-535) lists once (caps at 1000), maps `Contents` to `Delete`, calls `deleteObjects`, and ignores the per-object `Errors` array in the response. Tests use a `MockS3Client::create([...])` helper returning `Aws\Result` objects (`tests/Support/MockS3Client`).

Locked decisions:
- **F3**: `expiresAt` stays nullable; no forced default TTL. Expiry enforced only when set. Keep the timing-safe SHA-256 lookup intact.
- **F4**: Reuse the existing `security` `CsrfMiddleware`/`CsrfTokenManager` — do NOT build new CSRF machinery. Apply via the `#[Middleware]` attribute on the framework's own state-changing admin routes.
- **F7**: Min/Max/Between operate numerically when the field is numeric; string-length mode preserved for non-numeric strings. Coordinate with `is_numeric` ordering.

Cross-tier sequencing note (Job.php is shared by Tier 1, Tier 2, and Tier 3 — Tier1/Tier2 ALREADY MERGED on this branch):
- Tier 1 hardened `Job::serialize()`/`unserialize()` (HMAC envelope via `JobEnvelope`). Tier 2 wired container access onto `AsyncObserverJob`/`Worker`. Tier 3 (this plan, Task 009) changes which fields the concrete jobs hold and how `handle()` resolves services.
- **Verified against the MERGED code (do NOT plan against an imagined pre-merge state):** `Worker::work()` injects the container/envelope ONLY for `AsyncObserverJob` via an `instanceof AsyncObserverJob` gate (lines 46-50); the seam is the `setContainer()`/`setJobEnvelope()` SETTERS on `AsyncObserverJob`, NOT a resolver argument; `Job::handle()`/`JobInterface::handle()` is parameterless `handle(): void`; `Job::serialize()` is still `serialize($this)`.
- **Therefore Task 009 must WIDEN the seam, not just "consume" it:** lift the container/envelope-aware contract to a shared interface (`ContainerAwareJobInterface`, or base-`Job` setters) implemented by webhook/notification jobs, and widen the `Worker::work()` gate from `instanceof AsyncObserverJob` to that shared interface so the new jobs actually receive the container in the real Worker path. Do not add a parallel resolver or a serialized container property. Keep the HMAC envelope intact. Tests must drive through `Worker::work()` to prove the gate was widened (not just call `setContainer()` manually).

Cross-tier note (ConnectionInterface dialect accessor — shared by Tier 2 F9 and Tier 3 F10 — ALREADY LANDED):
- **Verified against MERGED code:** `ConnectionInterface::driverName(): string` ALREADY EXISTS (lines 54-62) and is implemented across `MySqlConnection` (`'mysql'`), `PgSqlConnection` (`'pgsql'`), `ReadWriteConnection` (delegates), and test stubs (`'sqlite'`). Tier 3 Task 010 (F10 session upsert) simply CALLS `driverName()` to pick the upsert dialect — there is nothing to add. Do NOT re-add the accessor.

### Follow-up findings (Tasks 013–020)

Tasks 013–020 are a second wave: gap-audit findings plus items dropped from the original consolidation, now folded into this tier because they are the same shape (focused bug-fix follow-ups against existing packages, no new packages). They were each independently source-verified at planning time; drift from the agent-reported descriptions is noted inline below and in the relevant task files.

Confirmed source facts (read at planning time):
- **013** `MarkdownRepository::getRawMarkdown()` (`docs-markdown`, lines 43-53) concatenates the docs path with the caller-supplied id (line 46) and only guards with `file_exists` (48) — a `../`-laden id reads any `.md` on disk. `DocsMarkdownException` has a single factory (`pageNotFound`); add a traversal factory and a `realpath()` containment check.
- **014** `CommandRunner::run()` (`devai`, lines 13-32) drains stdout fully (25), `fclose`s it, then drains stderr (27) — sequential, so a child filling its ~64KB stderr buffer deadlocks. Drain both concurrently or redirect stderr to a temp file; return shape `array{exitCode,stdout,stderr}` unchanged. Package has only `DevAiInstallException`; `run()` reports failure via the return array, not a throw.
- **015** `pubsub:listen` is pseudo-functionality: `PubSubListenCommand::execute()` calls `EventLoopRunner::run()` → `EventLoop::run()` with NOTHING registered, returning immediately; `AmphpConfig::shutdownTimeout()` + the `amphp.shutdown_timeout` config key (`config/amphp.php`) are dead; no `onSignal`/SIGINT anywhere. DEFAULT approach: implement a functional listener (subscribe via `SubscriberInterface`, register the `Subscription` stream on the loop, dispatch `Message`s, graceful SIGINT shutdown bounded by `shutdownTimeout()`). **Depends on 016** (needs working multi-channel subscribe). **MISSING WIRING (verified):** `marko/amphp` does NOT require `marko/pubsub` (so `SubscriberInterface` is not type-hintable) and `config/amphp.php` has NO `channels` key / `AmphpConfig::channels()` getter. The DEFAULT path must add `"marko/pubsub": "self.version"` to `amphp/composer.json`, a `'channels'` config key, and a `channels()` getter. (Coupling the event-loop package to the pubsub abstraction is a Question for the team.) ALTERNATIVE (product decision — see Risks): remove the command + dead config instead, which drops the new dependency and the 015→016 link.
- **016** Multi-channel subscribe is broken in BOTH drivers. `RedisSubscriber` uses only `$channels[0]` (line 24) / `$patterns[0]` (line 35), dropping the rest. `PgSqlSubscription::getIterator()` (lines 22-30) iterates listeners sequentially (nested foreach) so listener 0 blocks forever and later channels never deliver. The `Subscription` contract (one object multiplexing all channels) is correct; these are driver bugs. **DRIFT:** pgsql `psubscribe()` already throws `PubSubException::patternSubscriptionNotSupported('pgsql')` — it does NOT silently drop patterns; the multi-pattern requirement therefore applies to the REDIS driver only, and pgsql's only bug is the sequential multiplex.
- **017** `MySqlQueryBuilder::buildLimitOffsetClause()` (`database-mysql`, lines 943-956) emits the LIMIT and OFFSET fragments independently, so an offset with no limit produces a bare ` OFFSET n` (invalid in MySQL — pgsql tolerates it). Fix: when offset is set without a limit, emit `LIMIT 18446744073709551615 OFFSET n`. Props `?int $limitValue`/`?int $offsetValue`. **Cross-tier:** this file is also edited by Tier 1 (SQLi hardening) and Tier 5 (whereIn empty).
- **018** `Inertia::render()` (`inertia`, line 92) sets `'url' => $request->path()` — no query string; the middleware's 409 `X-Inertia-Location` (line 62) has the same defect. `InertiaMiddleware::handle()` runs `$next($request)` FIRST (line 24) and performs the asset-version check AFTER (49-65), so a 409 discards a response whose controller already consumed flash. **DRIFT detail:** the version check is gated to `method()==='GET'` and both versions non-null — keep that gating; the fix is ORDERING (pre-controller or reflash) + including the query string.
- **019** `SimpleErrorHandler::handleError()` (`errors-simple`, lines 101-127) special-cases only Deprecated/Notice; everything else (incl. `E_WARNING`) falls through to `handleException()` → `handle()`, which clears all output buffers, prints a 500 page, then `return true` (execution continues) — one recoverable warning destroys the in-progress response. And `handleNonFatal()` (87-99) opens `if (!isCli()) return;`, silently dropping non-fatal errors under web SAPI. Fix: report recoverable warnings non-destructively (no buffer teardown / no 500 page) and surface non-fatal errors in web SAPI. **Coordination:** Tier 2 Task 002 edits errors-ADVANCED and references SimpleErrorHandler as a model (different package — consistency check, no shared-file rebase).
- **020** `PgSqlQueryBuilder::insert()` (`database-pgsql`, lines **544-577** — original 451-480 DRIFTED) hardcodes `RETURNING "id"` (571) and reads `$result[0]['id']` (576), returning a wrong/zero value for any table whose PK is not `id` (the entity layer supports `#[Column(primaryKey: true)]` on any property). Fix: emit `RETURNING <actual-pk>` and read it back. **CORRECTION (verified against MERGED code):** there is NO Tier 2 `insertBatch`/PK-aware-RETURNING helper to consume — only `driverName()` landed. The fluent builder has no entity metadata (just `$table`+`$data`), so this task must INTRODUCE the PK signal as an additive optional `?string $primaryKey = null` param on `insert()` (default `'id'`, with the mysql builder updated for interface parity). No silent `?? 0` — a missing PK key in the RETURNING row is a loud error.

Cross-tier file-sharing note (query builders — Tier1/Tier2 ALREADY MERGED; verified line numbers):
- `MySqlQueryBuilder.php` was edited by Tier 1 (SQLi) — `buildLimitOffsetClause()` is now at lines **1068-1081** (Task 017's original 943-956 has drifted). Tier 5 (whereIn empty) is a separate clause. Task 017 stays surgically scoped to `buildLimitOffsetClause()`.
- `PgSqlQueryBuilder.php`: Tier 2 added `ConnectionInterface::driverName()` but did NOT add an `insertBatch`/PK-aware-RETURNING helper to this builder. `insert()` is now at lines **544-577** (Task 020's original 451-480 has drifted), still hardcoding `RETURNING "id"` (571) and reading `$result[0]['id']` (576). **There is no Tier 2 helper for Task 020 to consume** — Task 020 must introduce the PK signal itself (additive `?string $primaryKey = null` on `insert()`, default `'id'`). Re-run the affected builder's suite before committing.

## Scope

### In Scope
- F1 CORS wildcard+credentials hardening and `Vary: Origin`.
- F2 Media MIME-from-content derivation and Imagick raster-format allowlist.
- F3 API token optional expiry on create + expiry enforcement in the guard.
- F4 Wiring the existing security CSRF middleware onto admin-panel's state-changing routes (login/logout) and exposing a token to the login view. (admin-api is GET-only — out of scope, no state-changing route to protect.)
- F5 Casting POST/query scalar params and producing a loud 4xx/validation error for missing/invalid required params.
- F6 `preg_quote` of literal route segments and consistent param decoding.
- F7 Numeric-aware Min/Max/Between and loose In/NotIn matching for numeric strings.
- F8 Correct cron field parsing, DOW 0/7 alias, DOM+DOW OR semantics, and a loud error on malformed expressions.
- F9 Serializable webhook/notification jobs that resolve services at handle-time.
- F10 Atomic session upsert and loud write-after-close.
- F11 One canonical query normalization shared by page-cache store and purge.
- F12 S3 root-prefix fix, continuation-token paging for list/delete, and loud per-key delete errors.
- 013 docs-markdown path-traversal containment (`realpath()` under docs root, loud rejection).
- 014 devai CommandRunner concurrent stdout/stderr drain (no pipe deadlock).
- 015 amphp `pubsub:listen` becomes a functional listener (subscribe, dispatch, graceful SIGINT shutdown using `shutdown_timeout`); OR removal of the command + dead config if the maintainer chooses the alternative.
- 016 pubsub multi-channel subscribe correctness in both redis (all channels/patterns) and pgsql (concurrent listener multiplex).
- 017 MySQL valid SQL for offset-without-limit.
- 018 Inertia full-URL (path+query) in the page object and 409 location, and version-check ordering that does not drop flash.
- 019 errors-simple non-destructive handling of recoverable warnings and surfacing of non-fatal errors in web SAPI.
- 020 pgsql `insert()` returns the table's actual primary key (consuming Tier 2 Task 009's mechanism, not a competing one).

### Out of Scope
- New packages, new drivers, or new public abstractions beyond what each fix requires.
- Tier 1 unserialize hardening and Tier 2 queue plumbing (separate plans; see sequencing note).
- Rewriting the admin login template package(s) beyond passing a CSRF token value the controller already exposes data to.
- Changing the CSRF token storage/encryption design.

## Success Criteria
- [ ] CORS never emits `Allow-Credentials: true` with a wildcard origin, and reflecting responses carry `Vary: Origin`.
- [ ] Media uploads derive MIME from content; Imagick rejects non-allowlisted/format-mismatched input loudly.
- [ ] API tokens can be created with an optional expiry; expired tokens are rejected; null-expiry tokens stay valid.
- [ ] admin-panel state-changing POST routes (login/logout) reject requests without a valid CSRF token and accept them with one; the GET login form stays reachable without a token.
- [ ] Router fills typed scalar action params from POST/query and returns a clear 4xx/validation error (not a raw TypeError) for missing/invalid required params.
- [ ] Route literals match literally (dot, `#`, regex meta) while `{param}` routes still work.
- [ ] `integer|min:18` accepts `"25"` and rejects `"5"`; string-length mode still works for non-numeric values; In/NotIn match numeric strings against numeric allow-lists.
- [ ] Cron expressions evaluate per standard semantics (DOW 7, `a-b/n`, `1-5,10`, DOM+DOW OR) and malformed expressions throw loudly.
- [ ] Webhook and notification jobs serialize/unserialize cleanly and still dispatch.
- [ ] Concurrent-ish session writes for one id don't error or drop the row; writes after `save()` fail loudly.
- [ ] A URL containing a space or `+` stores and purges under the same page-cache key.
- [ ] S3 prefixed-root listing returns entries; >1000-object listing and deletion are handled; per-key delete errors surface loudly.
- [ ] docs-markdown rejects path-traversal ids loudly while legitimate ids read and clean-but-missing ids still throw pageNotFound.
- [ ] devai CommandRunner returns both streams and the exit code without hanging when the child writes >64KB to stderr.
- [ ] amphp `pubsub:listen` subscribes to configured channels, dispatches received messages, and shuts down gracefully within `shutdown_timeout` (OR the command + dead config are removed per the maintainer's product decision).
- [ ] pubsub `subscribe('a','b')` delivers a message published to a non-first channel in both drivers; redis `psubscribe` honors all patterns.
- [ ] MySQL `offset()` without `limit()` produces valid SQL (no bare OFFSET); limit+offset is unchanged.
- [ ] Inertia page `url` and the 409 `X-Inertia-Location` include the query string; an asset-version mismatch returns 409 without losing flash.
- [ ] errors-simple does not replace the response with a 500 page on a recoverable warning, and non-fatal errors are reported (not dropped) in web SAPI.
- [ ] pgsql `insert()` returns the correct generated key for a non-`id` primary key.
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | CORS: reject wildcard+credentials, add `Vary: Origin` | - | completed |
| 002 | Media: derive MIME from content; Imagick raster-format allowlist | - | completed |
| 003 | API tokens: optional expiry on create + guard enforcement | - | completed |
| 004 | Admin CSRF: wire security CsrfMiddleware onto admin-panel login/logout + login token (admin-api GET-only, out of scope) | - | completed |
| 005 | Router: cast POST/query scalars; loud error for missing/invalid required params | - | completed |
| 006 | Routing: `preg_quote` literal segments + consistent param decoding | - | completed |
| 007 | Validation: numeric-aware Min/Max/Between; loose In/NotIn for numeric strings | - | completed |
| 008 | Scheduler: correct cron parsing, DOW 0/7, DOM+DOW OR, loud malformed error | - | completed |
| 009 | Queue: serializable webhook/notification jobs resolving services at handle-time | - | completed |
| 010 | Session: atomic DB upsert + loud write-after-close | - | completed |
| 011 | Page-cache: single canonical query normalization for store and purge | - | completed |
| 012 | S3: root-prefix fix, continuation-token paging, loud per-key delete errors | - | completed |
| 013 | docs-markdown: reject path-traversal page IDs (realpath containment) | - | completed |
| 014 | devai: drain stdout/stderr concurrently to avoid pipe deadlock | - | completed |
| 015 | amphp: functional pubsub:listen (subscribe/dispatch/graceful shutdown) — OR remove command+dead config (product decision) | 016 | completed |
| 016 | pubsub: multi-channel subscribe in both redis and pgsql drivers | - | completed |
| 017 | MySQL: valid SQL for offset without limit | - | completed |
| 018 | Inertia: include query string in url + version check that preserves flash | - | completed |
| 019 | errors-simple: non-destructive recoverable warnings + surface web non-fatal errors | - | completed |
| 020 | pgsql: insert() returns the actual primary key via additive ?string $primaryKey param (no Tier 2 helper exists to consume) | - | completed |

Tasks 001–012 are file-cluster-isolated with no inter-task code dependencies and may run fully in parallel. Among the follow-up wave, **015 depends on 016** (the functional listener needs working multi-channel subscribe), so run 016 first; 013, 014, 017, 018, 019, 020 are mutually independent. Cross-plan ordering constraints (not in-plan dependencies): Tier 1 + Tier 2 are ALREADY MERGED on this branch — read the current files. Task 009 must WIDEN the existing `instanceof AsyncObserverJob` container-seam gate to a shared interface (not just consume a Tier 2 resolver). Task 017 stays scoped to `buildLimitOffsetClause()` (now lines 1068-1081). Task 020 must INTRODUCE its own PK signal (additive param) — Tier 2 added only `driverName()`, NOT a PK-aware RETURNING helper, so there is nothing to consume.

## Architecture Notes
- **Loud errors**: every new rejection path (F1 config combo, F2 disallowed format, F3 expired token where a throw is appropriate, F5 missing param, F8 malformed cron, F10 write-after-close, F12 delete errors) raises a `MarkoException` subclass with `message`/`context`/`suggestion`, or returns a deliberate 4xx Response where the contract is HTTP-shaped (F5 router, F4 CSRF via the existing `CsrfTokenMismatchException`).
- **Config defaults in config files only**: F2's Imagick allowlist and F1's behavior derive from `config/*.php` getters that throw on missing keys — no hardcoded fallbacks in getters. New config keys (e.g. media-imagick allowed formats) ship in a `config/*.php` file with a typed config getter class mirroring `MediaConfig`/`CorsConfig`.
- **Interface/driver split**: F9 jobs resolve concrete services from the container at `handle()`; they store only scalars/value-objects/ids. NOTE (verified): the container seam is `setContainer()`/`setJobEnvelope()` setters that `Worker::work()` injects ONLY for `AsyncObserverJob` (an `instanceof` gate). Task 009 must lift that seam to a shared `ContainerAwareJobInterface` (or base-`Job` setters) and widen the Worker gate so webhook/notification jobs also receive the container — otherwise they build but never get a container in the real Worker path.
- **No new public abstractions** unless required: F3 extends `TokenManager::createToken()` with an optional nullable `expiresAt` parameter (additive, default null) and adds an expiry check inside `TokenGuard`; F4 adds a composer dependency on `marko/security` and a `#[Middleware]` attribute usage.
- **Driver-appropriate SQL**: F10's upsert must branch on the connection's dialect (`ON DUPLICATE KEY UPDATE` for MySQL, `ON CONFLICT (id) DO UPDATE` for Postgres/SQLite) via `ConnectionInterface::driverName()`, which ALREADY EXISTS (verified, lines 54-62, returns `'mysql'`/`'pgsql'`/`'sqlite'`). F10 simply calls it — do not add or re-add any accessor.
- **Test patterns**: Pest `it(...)` in `tests/Unit` or `tests/Feature`; reuse existing fakes from `marko/testing` and the `MockS3Client` helper for F12; follow expectation-chaining and `->and()` conventions; loud-error tests assert message/context/suggestion populated.

## Risks & Mitigations
- **F4 needs a new composer dependency** (`marko/security`) on admin-panel only (admin-api is GET-only and out of scope). Mitigation: add `"marko/security": "self.version"` to admin-panel's `composer.json` require; do not hardcode a version. If introducing a hard dependency is undesirable, fall back to declaring the middleware by class-string only and documenting the requirement — flagged for user input below.
- **F10 dialect detection (RESOLVED)**: `ConnectionInterface::driverName()` ALREADY EXISTS on the merged branch (lines 54-62) and returns `'mysql'`/`'pgsql'`/`'sqlite'`. Task 010 just calls it; no accessor to add, no collision. Never sniff SQL or class names.
- **F9 container-seam widening (was "cross-tier rebase")**: the merged `Worker::work()` injects the container ONLY for `AsyncObserverJob` (`instanceof` gate, lines 46-50) via `setContainer()`/`setJobEnvelope()` setters; `handle()` is parameterless. Mitigation: Task 009 lifts the seam to a shared `ContainerAwareJobInterface` (or base-`Job` setters) and widens the Worker gate so webhook/notification jobs receive the container in the real Worker path. Tests drive through `Worker::work()` to prove the gate widened, not just a manual `setContainer()`. The HMAC `JobEnvelope` stays intact.
- **F2 finfo availability**: deriving MIME via `finfo` requires the fileinfo extension. Mitigation: it is bundled/enabled by default in PHP 8.5; guard with a loud error if `finfo_open` is unavailable.
- **F6 decode coordination**: url-decoding matched params could double-decode if `Request::path()` later changes. Mitigation: decode in exactly one place (the matcher/route param extraction) and add a test asserting a `%20`-encoded segment decodes once.
- **F5 validation-vs-router boundary**: returning a validation error from the router must not couple routing to the validation package. Mitigation: return a plain 4xx `Response` (or a routing-owned exception mapped to 4xx), not a `marko/validation` type.
- **⚑ 015 amphp implement-vs-remove (PRODUCT DECISION — needs maintainer input)**: `pubsub:listen` is currently pseudo-functionality (empty event loop, dead `shutdown_timeout` config, no SIGINT handler). The DEFAULT plan is to implement a real listener (subscribe → register on the loop → dispatch → graceful SIGINT shutdown bounded by `shutdown_timeout`), which depends on Task 016. The ALTERNATIVE, per the no-pseudo-functionality principle, is to REMOVE the command, `EventLoopRunner`, and the dead `shutdown_timeout` config + `AmphpConfig::shutdownTimeout()`. **Confirm with the maintainer which path to take before Task 015 is implemented.** If removal is chosen, Task 015 no longer depends on Task 016 (and 016 stands alone as a driver-correctness fix).
- **015 functional-listener feasibility**: a real async listener requires registering the `Subscription` stream on the Revolt `EventLoop` and a working `onSignal` shutdown. Mitigation: tests assert observable behavior (channels subscribed, message dispatched, shutdown bounded by timeout) via injected fakes/handlers, not a specific loop-registration mechanism; if a full integration listener proves environment-fragile, the fall-back is the removal path above.
- **014 deadlock test fragility**: a genuine pipe deadlock is timing/buffer-size dependent. Mitigation: drive it with a tiny inline child writing >64KB to stderr under a bounded timeout; if environment-fragile, mark that one case `->group('integration')` and keep the captured-output + exit-code assertions as plain unit tests.
- **017 / 020 cross-tier query-builder rebase**: Tier1/Tier2 are merged. `buildLimitOffsetClause()` moved to lines 1068-1081 (Task 017); `insert()` moved to 544-577 (Task 020). Mitigation: keep each change scoped to the single method it touches; Task 020 INTRODUCES the PK signal (additive `?string $primaryKey = null` param, default `'id'`, mysql builder updated for parity) since Tier 2 added only `driverName()` and no PK-RETURNING helper; re-run the affected builder's suite before committing.
- **013 realpath ordering**: `realpath()` returns `false` for nonexistent paths, so the containment check must be ordered so a clean-but-missing id still yields `pageNotFound` (not the traversal exception) while a `../` id resolving to a real outside file yields the traversal exception. Mitigation: explicit test for each path (legit, traversal, clean-missing).
