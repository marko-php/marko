# Devil's Advocate Review: tier3-medium-fixes

Reviewed all 20 task files (001-020) and `_plan.md` against the CURRENT (post-Tier-1/Tier-2-merged) source in `packages/`. Tier 1 and Tier 2 are already merged on this branch, which has shifted several of the facts the tasks were written against. Findings below are grouped by severity. Critical and Important items have been applied to the task files and `_plan.md`; Minor items and Questions are informational only.

## Critical (Must fix before building)

### C1 — Task 009: the container seam is gated behind `instanceof AsyncObserverJob`; the new jobs will never receive a container (integration-completeness failure)
The task says "mirror `AsyncObserverJob`'s resolver pattern" and "consume whatever container/resolver seam Tier 2 lands." But reading the merged code:
- `Worker::work()` (`packages/queue/src/Worker.php` lines 46-53) injects the container **only** for `AsyncObserverJob`:
  ```php
  if ($job instanceof AsyncObserverJob) {
      $job->setContainer($this->container);
      $job->setJobEnvelope($this->jobEnvelope);
  }
  $job->incrementAttempts();
  $job->handle();
  ```
- The seam is the `setContainer()`/`setJobEnvelope()` setters on `AsyncObserverJob` (not on the base `Job`, not on `JobInterface`).
- `Job::handle()` / `JobInterface::handle()` is `handle(): void` — **zero arguments**. There is NO resolver parameter to "mirror."

Consequence: if `DispatchWebhookJob`/`SendNotificationJob` are refactored to resolve services from a container at `handle()`, the Worker will NOT call `setContainer()` on them (the `instanceof AsyncObserverJob` gate excludes them), so `$this->container` stays null and `handle()` either throws an NPE-style "called without a container" error or silently does nothing. The feature would build green in unit tests (which can call `setContainer()` manually) but fail in the real Worker path.

Fix applied: Task 009 now requires lifting the container/envelope seam to a shared abstraction (a `ContainerAwareJobInterface` / base-`Job` setter, with the exact name to be confirmed against the Tier 2 wiring) and widening the `Worker::work()` gate from `instanceof AsyncObserverJob` to the shared interface, so all container-aware jobs (async-observer, webhook, notification) receive the container the same way. Added an explicit requirement asserting the Worker injects the container into these jobs (not just a manual unit-level `setContainer()`).

### C2 — Task 020: there is NO Tier 2 `insertBatch`/PK-aware RETURNING helper to consume; the premise is stale
The task instructs: "COORDINATE with Tier 2 Task 009 ... If Task 009 already introduces a PK-aware RETURNING helper ... THIS task MUST CONSUME that helper rather than inventing a second PK-resolution path." Reading the merged code:
- `PgSqlQueryBuilder` (`packages/database-pgsql/src/Query/PgSqlQueryBuilder.php`) has **no** `insertBatch()` method and **no** PK-resolution helper. The only Tier 2 Task 009 artifact that landed is `ConnectionInterface::driverName()` (now present, returns `'mysql'`/`'pgsql'`/`'sqlite'`).
- `insert()` is now at lines **544-577** (NOT the cited 451-480). It hardcodes `RETURNING %s` with `$this->quoteIdentifier('id')` at line **571** and reads `(int) ($result[0]['id'] ?? 0)` at line **576**.
- The builder only knows `$table` and the `$data` array — it has **no entity metadata** and therefore **no way to know the PK column name** from its current inputs. (The entity layer's PK knowledge lives in `Repository`/`EntityMetadata`; `Repository::insert()` doesn't even call `QueryBuilder::insert()` — it builds raw SQL + `lastInsertId()`. So the query-builder `insert()` is a standalone fluent API with no PK signal.)

Consequence: there is nothing to "consume." The task as written would leave the implementer searching for a non-existent helper. Worse, the real design question — "how does the fluent builder learn the PK column?" — is unanswered. The `QueryBuilderInterface::insert(array $data): int` signature (`packages/database/src/Query/QueryBuilderInterface.php:381`) has no PK parameter, and it is shared by the mysql builder too.

Fix applied: Task 020 rewritten to (a) correct the line numbers (insert 544-577, RETURNING at 571, read at 576), (b) drop the "consume the Tier 2 helper" framing and instead require the task to INTRODUCE the PK signal explicitly — the recommended approach is an additive optional `?string $primaryKey = null` parameter on `insert()` (and the interface) defaulting to `'id'` for backward compatibility, with the mysql builder updated for interface parity (it ignores the param since it uses `lastInsertId()`), and a loud error (not `?? 0`) when the RETURNING row lacks the expected PK key. Flagged the interface-signature change as a coordination point.

### C3 — Task 010: `ConnectionInterface::driverName()` already exists; the task's "no accessor today, add it" instructions are stale and will cause a duplicate-method conflict
The task body and `_plan.md` repeatedly state "ConnectionInterface has NO dialect accessor today" and "if Tier 2 Task 009 has not yet landed, add the accessor with the EXACT signature." Reading the merged code:
- `ConnectionInterface::driverName(): string` is **already present** (`packages/database/src/Connection/ConnectionInterface.php` lines 54-62) and implemented across `PgSqlConnection` (`'pgsql'`), `MySqlConnection` (`'mysql'`), `ReadWriteConnection` (delegates to write), and the test stubs (`'sqlite'`).

Consequence: an implementer following the stale instruction might re-add the accessor and collide. At minimum the task wastes effort confirming a non-existent gap.

Fix applied: Task 010 rewritten to reference `ConnectionInterface::driverName()` as an **existing** method returning `'mysql'`/`'pgsql'`/`'sqlite'`, and to simply call it for dialect selection. Removed the "add the accessor if Tier 2 hasn't landed" branch.

## Important (Should fix before building)

### I1 — Task 015: `marko/amphp` does not depend on `marko/pubsub`, and there is no `channels` config key/getter — the functional listener cannot be wired as written
The DEFAULT (locked) approach constructor-injects `SubscriberInterface` and "the configured channel list (channels come from a `config/*.php` getter)." Reading the merged code:
- `packages/amphp/composer.json` requires only `marko/core` + `marko/config` (plus amp/revolt). It does **not** require `marko/pubsub`, so `SubscriberInterface` (namespace `Marko\PubSub`) is not even autoloadable/type-hintable in the amphp package.
- `packages/amphp/config/amphp.php` has only `'shutdown_timeout'` — there is **no** `channels` key, and `AmphpConfig` has no `channels()` getter.

Consequence: the worker can't type-hint `SubscriberInterface` (missing dep) and has no config source for channels (missing key + getter). The task would stall on integration.

Fix applied: Task 015 now explicitly requires (a) adding `"marko/pubsub": "self.version"` to `packages/amphp/composer.json` require, and (b) adding a `'channels' => [...]` key to `config/amphp.php` plus an `AmphpConfig::channels(): array` getter (throws `ConfigNotFoundException` when missing — no hardcoded fallback). Noted the architectural coupling (amphp event-loop package now depends on the pubsub abstraction) as a Question for the team.

### I2 — Task 017: cited line numbers are stale (943-956 → actually 1068-1081); `count()` save/restore reference also drifted
`MySqlQueryBuilder::buildLimitOffsetClause()` is now at lines **1068-1081** (the task cites 943-956). The bug is exactly as described (independent LIMIT/OFFSET fragments; bare ` OFFSET n` with no LIMIT). The `count()` save/restore of limit/offset cited at "620-632" should not be relied on by line number.

Fix applied: Task 017 line references corrected to 1068-1081 and the verification note updated; removed the stale 620-632 count() line citation, replacing it with a behavioral note.

### I3 — Task 020: cited line numbers stale (451-480 → 544-577); `?? 0` masking must become a loud error
Beyond C2: the `insert()` method is at 544-577, not 451-480. The current `(int) ($result[0]['id'] ?? 0)` silently returns 0 on a missing key. The task's "no silent `?? 0`" instruction is correct but must be made a concrete requirement (a `MarkoException` subclass — the pgsql package has an exceptions dir; confirm a suitable factory or add one).

Fix applied: line numbers corrected; added an explicit requirement that a missing/absent PK key in the RETURNING row raises a loud exception (message/context/suggestion) rather than returning 0.

### I4 — Task 009: `DispatchWebhookJob::handle()` re-enqueues `new self(...)` with live services — the retry path must be reworked to the new payload-only shape, and `module.php` needs no boot wiring but the container must resolve the new collaborators
`DispatchWebhookJob::handle()` (lines 45-56) constructs `new self($this->payload, $this->dispatcher, ..., $this->attemptNumber + 1)` and calls `$this->queue->later($delay, $nextJob)`. Once the job holds only `WebhookPayload` + `attemptNumber`, the retry `new self(...)` must construct the payload-only form, and the `QueueInterface` (needed for `later()`) must come from the container at handle-time, not a constructor property. The requirement "re-enqueues a retry job that is itself serializable" exists but the task should call out that the retry construction and the `queue->later()` call both move to handle-time container resolution.

Fix applied: Task 009 context expanded to require that the retry re-enqueue builds the payload-only job and resolves `QueueInterface` from the container inside `handle()`, and that `SendNotificationJob` resolves `NotificationSender` from the container (its `module.php` boot already registers channels — no change needed there, but the job must not hold the sender).

### I5 — Task 018: there is no `queryString()`/`fullUrl()` accessor on `Request`; path+query must be reconstructed, with the SAME encoding caveat as F11
`Request` (`packages/routing/src/Http/Request.php`) exposes `path()` (strips at `?`, lines 50-56) and `query()` (returns the parsed array). There is **no** raw-query-string accessor. The implementer must reconstruct path+query either from `$server['REQUEST_URI']` (which still contains the raw query) or by re-encoding the `query` array — and the latter risks the exact `+`/`%20` mismatch that Task 011 (F11) fixes for page-cache. The task says "use whatever yields path-plus-query consistently" but doesn't flag the missing accessor or the encoding hazard.

Fix applied: Task 018 note sharpened to require reading path+query from `REQUEST_URI` (preserving the raw query exactly, no re-encode) so the URL matches what the browser sent, and added a requirement that a `+` or `%20` in the query round-trips unchanged into the Inertia `url`.

## Minor (Nice to address — NOT applied)

- **M1 — Task 010 exception semantics:** flipping `Session::$started = false` in `save()` makes a post-`save()` `set()` throw the existing `SessionNotStartedException` ("session not started"), which is slightly misleading for a developer who DID start the session. A dedicated `SessionClosedException` (or a distinct factory message naming "closed after save") would be clearer. The task permits reusing the existing exception, which is acceptable.
- **M2 — Task 008 static API:** `CronExpression::matches()`/`matchField()` are `static`. The loud-error throw fits a static method fine, but if the exception factory ever needs injected context (it doesn't today) the static design would constrain it. No action needed.
- **M3 — Task 003 caching:** `TokenGuard::resolveTokenEntity()` caches `$this->resolvedToken` (line 66). The implementer should decide whether an expired token caches as `null` or as the entity (with the expiry check applied in `user()`/`hasAbility()`). Either is fine; just be consistent so a second `user()` call doesn't re-resolve.
- **M4 — Several tasks cite approximate line ranges** (001 ~28-44, 002 ~27-55, 005 ~79-117, 006 ~39-45) that all verified accurate; no change needed. Only 017 and 020 had material drift (fixed above).

## Questions for the Team

- **Q1 — Task 004 (product decision, leave default):** The default is to add `"marko/security": "self.version"` to `admin-panel/composer.json` and wire `CsrfMiddleware` on `authenticate()`/`logout()` via the `#[Middleware]` attribute. Verified feasible: admin-panel already requires `marko/session`; `marko/security` brings the `CsrfTokenManagerInterface` binding (session + encryptor). Confirm you want the hard `marko/security` dependency rather than the class-string fallback. (Left as default per instructions.)
- **Q2 — Task 015 (product decision, leave default):** The default is to IMPLEMENT a functional `pubsub:listen` listener (depends on 016), which requires `marko/amphp` to take a NEW dependency on `marko/pubsub` (the event-loop lifecycle package would then couple to the pubsub abstraction) and to add a `channels` config key. Confirm this coupling is acceptable, or choose the documented ALTERNATIVE (remove the command + dead config), which would drop the dep and the 015→016 dependency. (Left as default per instructions.)
- **Q3 — Task 020 PK signal design:** The recommended fix is an additive `?string $primaryKey = null` param on `insert()` (default `'id'`). Confirm this is preferred over a stateful builder method (e.g. `returning('uuid')`) — the param keeps the change surgical and backward-compatible, but a builder method would read more fluently. Either keeps the `QueryBuilderInterface` change additive.
