# Devil's Advocate Review: tier5-low-hardening

Reviewed all 24 task files (001–024) plus `_plan.md` against the CURRENT source on
`feature/tier5-low-hardening` (Tier 1–4 merged). Re-verified every load-bearing class/method/line
the tasks cite. The plan is high quality and most tasks are accurate, but the cross-tier rebase
introduced three real problems (two Critical, one Important) plus a few line-drift / scoping items.

## Critical (Must fix before building)

### C1 — Task 020: `whereNotIn()` does not exist anywhere (the task's "twin" is fictional)
The task fixes empty `whereIn([])` AND empty `whereNotIn([])` on both builders. But there is **no
`whereNotIn()` method** in the codebase:
- `packages/database-mysql/src/Query/MySqlQueryBuilder.php` — only `whereIn()` (line 233); no
  `whereNotIn`, no `whereNotIns` property, no `NOT IN` compile branch.
- `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` — same (whereIn at line 232).
- `packages/database/src/Query/QueryBuilderInterface.php` — declares `whereIn()` (line 94) only.
- `packages/database/src/Repository/RepositoryQueryBuilder.php` — proxies `whereIn()` only.

A worker trying to "apply the same fix shape to `whereNotIn`" will either be blocked (nothing to
edit) or invent a brand-new `whereNotIn()` method on the interface + both builders + the repository
proxy — which is (a) a public-interface change explicitly listed **Out of Scope** in `_plan.md`, and
(b) far larger than a single low-severity hardening task. Fix: scope Task 020 to empty-`whereIn` only.

### C2 — Task 023: cites stale RetryCommand code; would reintroduce raw `unserialize`
The task's Context/Patterns describe `$job = unserialize($failedJob->payload)` and say "keep
serialize/unserialize ... unchanged." The CURRENT `RetryCommand` (post Tier 1 JobEnvelope HMAC seam)
is:
```php
$job = unserialize($this->jobEnvelope->verifyAndUnwrap($failedJob->payload));
```
in BOTH `retryJob()` (line 101) and `retryAll()` (line 73), with `JobEnvelope $jobEnvelope` injected
(line 24) and `execute()` declaring `@throws SerializationException`. A worker who follows the task
literally ("after unserializing") could drop the `verifyAndUnwrap()` and re-bind raw `unserialize`,
silently undoing the Tier 1 HMAC integrity check. The `resetAttempts()` insertion is correct, but it
must go AFTER `verifyAndUnwrap(...)` and BEFORE `queue->push(...)`, preserving the envelope seam.
`Job::$attempts` / `JobInterface` confirmed exactly as the task states (reset method must be added).

## Important (Should fix before building)

### I1 — Task 008: `path()` `rawurldecode` double-decodes against Tier 3's RouteMatcher
The plan's "No Tier 3 collision on `Request::path()`" note is STALE and wrong. Tier 3 added
percent-decoding in `RouteMatcher::extractParameters()` (`packages/routing/src/RouteMatcher.php`
line 64: `rawurldecode($matches[$name])`) and a Tier-3 test that pins the contract:
`packages/routing/tests/RouteRegexEscapingTest.php` →
`it('rawurldecodes a matched parameter value exactly once', ...)` feeds the RAW path
`/search/hello%20world` to `match()` and expects `['query' => 'hello world']`.

The dispatch flow is `Router::match($request->method(), $request->path())`
(`packages/routing/src/Router.php` line 41). If Task 008 makes `path()` apply `rawurldecode`, the
matcher receives an already-decoded path and `extractParameters` decodes a SECOND time. Consequences:
- A value like `hello%2520world` → `path()` → `hello%20world` → `extractParameters` → `hello world`
  (double decode, data corruption; violates the Tier-3 "exactly once" contract).
- A `%2F` (encoded slash) decoded in `path()` becomes a literal `/`, changing the path STRUCTURE
  before matching and breaking routes / enabling path-segment confusion.

Fix: drop the `path()` decode entirely from Task 008. Per-parameter decoding is already correct in
the matcher. The Content-Type / Content-Length `header()` CGI-key fallback (the real bug) stays — it
does not interact with Tier 3 at all. Cast `REQUEST_URI` to string and keep the `?`-strip + `/`
default, but do NOT `rawurldecode`.

### I2 — Task 020: line numbers drifted post-rebase (re-anchor before editing)
The task cites the `IN (%s)` loop at "~824-838" (mysql) / "~825-834" (pgsql) and `whereIn()` at
"~207/206". Current reality:
- MySQL: `whereIns` property line 31, `whereIn()` line 233, compile loop lines 950-957
  (`array_fill(0, count($whereIn['values']), '?')` → `sprintf('%s IN (%s)', quoteIdentifier(col), implode)`).
- PgSQL: `whereIns` property line 32, `whereIn()` line 232, compile loop lines 958-967.
The cross-tier-rebase note already says "re-locate before editing," but the stated line anchors are
now misleading; update them so the worker doesn't edit the wrong block.

## Minor (Nice to address — NOT applied)

- **Task 003 line refs:** says `event`/`id` interpolation "~27-37" (that's `format()`), but the fix is
  in the CONSTRUCTOR (lines 12-17). Both are in `SseEvent.php`; the worker will find it. The
  `@throws JsonException` already on `format()` is separate from the new `@throws SseException` on the
  constructor. Cosmetic.
- **Task 004 `readonly class` watch:** `OpenSslEncryptor` currently has all-readonly promoted/declared
  props (`$config`, `$key`) yet is a plain `class` (not `readonly class`). Adding `private readonly
  string $cipher` keeps it all-readonly. Per code-standards rule #4 this would normally suggest
  `readonly class`, but the existing class already declines that (and `readonly class` is the
  extensibility trap to avoid on production `src/`). The worker should keep it a plain `class` with
  individual `readonly`, matching the existing file — do NOT promote to `readonly class`.
- **Task 014 factory signature:** confirmed `DocsException::searchFailed(string $reason)` takes ONE
  arg and does NOT accept a `previous`. The task already handles this conditionally ("otherwise
  include the offending query and the PDO message in message/context") — the worker must embed the
  PDO message in `$reason`; there is no `previous` channel.
- **Task 012 cache-file also has an unrestricted `unserialize`** (`FileCacheDriver::read()` line 301)
  guarded by an `is_array` check. It's out of this task's scope (and not in the plan), but worth a
  follow-up ticket for parity with Task 013's hardening.

## Questions for the Team (NOT applied)

- **Q1 (Task 020):** Is a real `whereNotIn()` desired at all? If yes, it should be its own task
  (interface + both builders + repository proxy + tests) and would change a public interface — which
  the plan currently forbids. For now Task 020 is scoped to empty-`whereIn` only; confirm that is the
  intended 1.0 scope.
- **Q2 (Task 001):** `WebhookDispatcher::dispatch()` `json_encode($body)` (line 26) lacks
  `JSON_THROW_ON_ERROR` and a `@throws JsonException`. Task 001 already edits this method to add the
  timestamp — should it also harden that pre-existing encode, or leave it for a separate pass?
