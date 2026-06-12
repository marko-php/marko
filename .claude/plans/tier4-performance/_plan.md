# Plan: Tier 4 — Performance / N+1 Fixes

## Created
2026-06-10

## Status
ready

## Objective
Eliminate N+1 query loops and missing `LIMIT 1` optimizations on hot lookup, attachment, admin-auth, Redis multi-key, and notification fan-out paths so each operation issues a bounded, batched number of queries/round-trips instead of one per row/key/recipient.

## Related Issues
none

## Discovery Notes

**New vs overlapping.** All five findings are new performance tasks. They touch files already created by other plans, but none of those plans address the per-row/per-key loops:
- `database-package` owns `Repository.php` (F1). F5 does NOT reuse `Repository::insertBatch()` (notifications have no Entity/auto-increment PK — see below); it only mirrors the multi-row VALUES placeholder shape. This plan only changes the `findOneBy`/`exists`/`existsBy`/`isColumnUnique` SQL and adds no new public surface there beyond `LIMIT 1`.
- `repository-lifecycle-events` adds the `EntityCreating/Created` dispatch in `insertBatch()`; F5 does not touch `insertBatch` so the event contract is unaffected.
- `orm-relationships` owns `eagerLoadRelationships()` — F1 must keep eager-loading semantics identical for `findOneBy` (it still returns a fully hydrated, eager-loaded entity via `$this->pendingRelationships`, just capped at one row; no `ORDER BY` introduced so the chosen row is unchanged).
- `cache-redis` owns `RedisCacheDriver.php` (F4). **Tier 1 added a `CacheValueSigner` HMAC envelope — `set()` stores `cacheValueSigner->wrap(serialize($value))` and `get()` returns `unserialize(cacheValueSigner->verifyAndUnwrap($data))`. F4's batch paths MUST route through the SAME signer (wrap on `setMultiple`, verify+unwrap on `getMultiple`) — not raw serialize/unserialize — or the existing round-trip tests fail with `TamperedCacheValueException` and tamper protection is silently stripped from the multi-key path.** Tier 1 also added an atomic `increment()` that is NOT signed; F4 does not touch it and rebases cleanly.
- `auth-package` / `admin-system` own `AdminUserProvider` and `RoleRepository` (F3). F3 adds one batch method to `RoleRepositoryInterface` + `RoleRepository` and rewires `loadRolesAndPermissions`. **Adding to the interface forces updating the `createMockRoleRepo` anonymous-class implementer in `AdminUserProviderTest.php` (and the `getPermissionsForRole` reflection list in `RoleRepositoryInterfaceTest.php`) in the same task, or the suite fatals.**
- `notification` owns `NotificationSender` + `DatabaseChannel` (F5). F5 adds a NEW `BatchChannelInterface` (opt-in) rather than modifying `ChannelInterface` — modifying the shared `ChannelInterface` would break `MailChannel`, the docs `SmsChannel`, and all third-party channels.
- `media` ships only `MediaRepositoryInterface` — **there is no concrete `MediaRepository` class** in the package. F2 adds `findMany` to the interface, rewires `AttachmentManager`, and updates the two in-repo test mocks that implement the interface (`AttachmentManagerTest`, `MediaManagerTest`); the single-query SQL implementation is the consumer's responsibility, documented in the contract.

**Exclusions that belong to other tiers (do NOT implement here):**
- `session-database` write path (DELETE+INSERT) — Tier 3 F10. Reference only.
- rabbitmq failed-job scan — Tier 2 F4. Reference only.
- Per-request discovery scan / discovery cache — separate `discovery-cache` plan. Reference only.

**How query-count is tested.** This codebase has no dedicated counting/fake `ConnectionInterface` in `marko/testing`. The established pattern (see `packages/database/tests/Feature/RepositoryCrudTest.php` ~lines 47-163) is an inline anonymous class implementing `ConnectionInterface` with a `public array &$queries` reference property that appends `['sql' => $sql, 'bindings' => $bindings, 'type' => 'query'|'execute']` on every `query()`/`execute()` call. **Tier 2 added `ConnectionInterface::driverName(): string` — every hand-written anonymous `ConnectionInterface` stub MUST implement it (returning e.g. `'sqlite'`) or it fatals at instantiation as abstract-incomplete. The reference stubs already do (RepositoryCrudTest line 159, RoleRepositoryTest line 256).** Tests then:
- assert `count($queries)` (e.g. exactly one SELECT regardless of N ids/roles),
- assert SQL substrings (`str_contains($sql, 'LIMIT 1')`, `WHERE id IN (?, ?, ?)`, a single multi-row `INSERT ... VALUES (?,?),(?,?)`),
- and return canned rows so the observable result (entity set / bool / permission set) can be asserted identical to the pre-change behavior.

For F4, the analogous pattern is the existing `MockRedisClient extends Predis\Client` stub (see `packages/cache-redis/tests/Unit/...RedisCacheDriverTest.php`) with public `$storage`/`$ttls`. F4 extends that mock to record `mget`, pipelined `setex`/`set`, and variadic `del` calls so batching can be asserted (call recorded once, not N times) while preserving the existing behavioral expectations (TTL stored, missing keys -> default/null).

Each task writes the behavioral (correct-result) assertions FIRST, then the query/round-trip-count assertions, in one TDD cycle.

## Scope

### In Scope
- F1 — `LIMIT 1` for `findOneBy`; `SELECT 1 ... LIMIT 1` early-exit for `exists`, `existsBy`, `isColumnUnique` in `packages/database/src/Repository/Repository.php`.
- F2 — Batch attachment hydration in `packages/media` (`AttachmentManager::findByAttachable`), adding a `findMany(array): array` batch method to `MediaRepositoryInterface` (no concrete impl exists in-package — only the interface + test mocks are updated; the WHERE-IN SQL is the consumer's responsibility per the documented contract).
- F3 — Single permissions query for all role ids in `packages/admin-auth` (`AdminUserProvider::loadRolesAndPermissions` + a new `getPermissionsForRoles(array $roleIds)` on `RoleRepositoryInterface`/`RoleRepository`); empty role-id list short-circuits with no query (never emits `IN ()`). Existing interface mocks updated in the same task.
- F4 — Batched Redis multi-key ops (MGET read, pipelined TTL writes, variadic DEL) in `packages/cache-redis/src/Driver/RedisCacheDriver.php`.
- F5 — Batched notification fan-out: a NEW opt-in `BatchChannelInterface` implemented by `DatabaseChannel` so recipients on the `database` channel persist via a single chunked multi-row INSERT (UUID PKs, built directly in `DatabaseChannel`); the sender groups per-recipient-resolved channels and falls back to per-recipient `send()` for non-batch channels. `ChannelInterface` is unchanged.
- (Optional, cheap) admin-auth `syncPermissions`/`syncRoles` per-row inserts wrapped in a single transaction + batched insert — folded into the F3 cluster if low-cost, else listed Out of Scope below.

### Out of Scope
- session-database write path (DELETE+INSERT) — Tier 3 F10.
- rabbitmq failed-job scan — Tier 2 F4.
- per-request discovery scan — `discovery-cache` plan.
- `CacheInterface::increment` (Tier 1, if present) — F4 does not touch it.
- Adding a reusable counting/spy `ConnectionInterface` to `marko/testing` (each test uses the established inline-anonymous-class pattern; promoting it to a shared fake is a separate refactor).

## Success Criteria
- [ ] F1: `findOneBy` SQL contains `LIMIT 1`; `exists`/`existsBy`/`isColumnUnique` issue a `SELECT 1 ... LIMIT 1` (no `SELECT *`, no full hydration, no eager-load) and return identical booleans.
- [ ] F2: `findByAttachable` returns the same `Media` set in attachment-id order and resolves them via a single `findMany` call (never per-id `find`) regardless of attachment count; `findMany` is added to `MediaRepositoryInterface` and all in-repo implementers.
- [ ] F3: an authenticated admin request resolves the same permission set with exactly one permissions query for N roles.
- [ ] F4: `getMultiple`/`setMultiple`/`deleteMultiple` preserve multi-key semantics (TTL, missing-key default/null) while issuing one MGET / one pipeline / one variadic DEL instead of N round-trips.
- [ ] F5: fanning a notification to N recipients on the `database` channel issues batched (chunked) multi-row inserts and persists all N notifications.
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | F1: `LIMIT 1` for `findOneBy` | - | pending |
| 002 | F1: `SELECT 1 ... LIMIT 1` for `exists`/`existsBy`/`isColumnUnique` | 001 | pending |
| 003 | F2: add `findMany` to `MediaRepositoryInterface` (+ test mocks) + single-call `findByAttachable` | - | pending |
| 004 | F3: `getPermissionsForRoles` batch query (+ interface mocks) + rewire `loadRolesAndPermissions` | - | pending |
| 005 | F3 (optional): transactional + batched `syncPermissions` (no `syncRoles`) | 004 | pending |
| 006 | F4: batched Redis multi-key ops (MGET / pipeline / variadic DEL) | - | pending |
| 007 | F5: new `BatchChannelInterface` + `DatabaseChannel::sendMany` + sender grouping | - | pending |

## Architecture Notes
- **F1 — `findOneBy`** currently delegates to `findBy($criteria)->first()`, fetching ALL matching rows + hydrating + eager-loading every one. Add a query path that appends `LIMIT 1` so the DB returns at most one row; the single returned entity must still be eager-loaded (preserve `orm-relationships` behavior). `findBy` itself is unchanged.
- **F1 — exists family** (`exists`, `existsBy`, `isColumnUnique`) currently delegate (`exists`→`find()`, `existsBy`→`findOneBy()`) or run `SELECT *`. **After Task 001 `findOneBy` still returns a hydrated, eager-loaded entity (capped at LIMIT 1), so leaving `existsBy` delegating would STILL hydrate for a bool — Task 002 must replace both bodies with dedicated probes, not keep delegating.** Replace with a boolean probe: `SELECT 1 FROM <table> WHERE ... LIMIT 1` via `connection->query()`, returning `count($rows) > 0` (or `=== 0` for `isColumnUnique`). No hydration, no eager-load. `exists($id)` keys on the primary-key column; `existsBy` builds the same property->column criteria mapping `findBy` uses; `isColumnUnique` (which is `protected`) keeps its `excludeId` AND-clause.
- **F2** — `AttachmentManager::findByAttachable` loops `mediaRepository->find($id)` once per id. Add `MediaRepositoryInterface::findMany(array $ids): array` (returns `array<Media>`; empty input -> empty array, no query) to the interface only — `marko/media` ships no concrete repository, so the single `WHERE id IN (...)` query is a documented consumer responsibility. `AttachmentManager` calls `findMany($mediaIds)` once, then re-orders the returned media to the attachment id list and skips ids with no matching media (preserves the current null-skip + ordering). Both in-package test mocks implementing `MediaRepositoryInterface` must add `findMany` or the suite fatals.
- **F3** — `loadRolesAndPermissions` loops `roleRepository->getPermissionsForRole($role->id)` per role inside `retrieveById()`, `retrieveByCredentials()`, and `retrieveByRememberToken()` (NOT `retrieveByToken`, which does not exist) — every authenticated admin request. Add `RoleRepositoryInterface::getPermissionsForRoles(array $roleIds): array` returning the deduplicated permission set across all roles via one `SELECT DISTINCT p.* ... INNER JOIN role_permissions rp ON ... WHERE rp.role_id IN (?, ?, ...)` with placeholder count == role-id count. Because it hydrates Permissions it `@throws EntityException` (like `getPermissionsForRole`); propagate that tag through `loadRolesAndPermissions` and the three `retrieveBy*` provider methods. Rewire `loadRolesAndPermissions` to collect non-null role ids, call once, dedupe permission keys. **Empty role-id list short-circuits to `[]` with no query — never emit `IN ()` (SQL syntax error).** The `createMockRoleRepo` implementer (a `readonly class`) in `AdminUserProviderTest.php` must add the method (keeping the class `readonly`) in the same task.
- **F3 optional (Task 005)** — `syncPermissions` does `DELETE` then one `INSERT` per permission id with no transaction. Wrap the DELETE + a single batched insert (or chunked multi-row INSERT) in a transaction so a mid-loop failure cannot leave a role half-synced. Apply the same to `syncRoles` if it exists with the same shape.
- **F4** — `RedisCacheDriver` multi-key methods loop single-key `get`/`set`/`delete`. Replace with: `getMultiple` -> one `mget(...prefixedKeys)` then map results (`unserialize(cacheValueSigner->verifyAndUnwrap($value))` for each hit, default for nulls), preserving input-key order and missing-key default. `setMultiple` -> a Predis pipeline issuing `setex` (TTL>0) or `set` (TTL 0) per pair in one round-trip, each value `cacheValueSigner->wrap(serialize($value))`; default TTL still applies. `deleteMultiple` -> one variadic `del(...prefixedKeys)` (the mock's `del` is already variadic). All keys validated via existing `validateKey`. **The HMAC signer MUST be preserved on both batch paths** (see Discovery Notes) — `@throws` includes `TamperedCacheValueException`. Return values unchanged (`bool`/iterable). `increment()` is untouched.
- **F5** — `NotificationSender::send` resolves channels per recipient (`$notification->channels($notifiable)`, which can differ per recipient) and calls `channel->send($notifiable, $notification)` once each; `DatabaseChannel::send` runs one raw INSERT. Add a NEW opt-in `BatchChannelInterface` (`sendMany(array $notifiables, NotificationInterface): void`) implemented by `DatabaseChannel` — do NOT add to `ChannelInterface` (breaks `MailChannel`/`SmsChannel`/third-party channels). The sender builds a channel-name -> recipients map from the per-recipient resolution (resolving each channel via `manager->channel($name)`, which throws `NotificationException::unknownChannel`), then for each `BatchChannelInterface` channel with >1 recipient calls `sendMany` once; otherwise falls back to per-recipient `send`. **Both the `sendMany` call and the fallback `send` calls must be wrapped in the SAME try/catch the current loop uses (ChannelException rethrown, other Throwable -> `NotificationException::sendFailed`).** `DatabaseChannel::sendMany` builds one chunked multi-row `INSERT INTO notifications (...) VALUES (...),(...)` directly (UUID PK per row via `generateUuid`, same column data as single-send: id/type/notifiable_type/notifiable_id/data/read_at/created_at), wrapping the WHOLE per-chunk build+execute in `try/catch (Throwable) -> ChannelException::deliveryFailed('database', ...)` so `random_bytes()` (`RandomException`) and `json_encode(JSON_THROW_ON_ERROR)` (`JsonException`) failures are wrapped too; `@throws ChannelException`. **No dependency on Tier 2 F9** — notifications use explicit UUID PKs, not auto-increment, so the `insertBatch`/`lastInsertId` pgsql bug cannot apply. Chunk size (a typed constant) keeps placeholder count within driver limits.

## Risks & Mitigations
- **Eager-load regression (F1):** capping `findOneBy` at one row must not drop eager loading. Mitigation: test asserts a `with()`-relationship is still populated on the single result.
- **No shared counting fake:** query-count assertions rely on inline anonymous `ConnectionInterface` stubs. Mitigation: each task's test description specifies the stub records `['sql','bindings','type']` and asserts on `count()` + SQL substrings, mirroring `RepositoryCrudTest`.
- **Predis pipeline/MGET mock fidelity (F4):** the existing `MockRedisClient` only stubs `get/set/setex/del/exists/keys/ttl/incr/expire` — it has NO `mget` and NO `pipeline` (but `del` is ALREADY variadic and flattens arrays). Mitigation: extend the mock to implement `mget` and `pipeline` (collecting queued commands), recording call counts; tests assert both behavior and that the batched method was invoked once. Do not re-add a variadic `del`.
- **HMAC signer preservation (F4) [CRITICAL]:** Tier 1 wraps stored values in a `CacheValueSigner` envelope. If the batch paths use raw serialize/unserialize, the existing `setMultiple`+`get` round-trip tests fail with `TamperedCacheValueException` and tamper protection is lost. Mitigation: `setMultiple` wraps each value via `cacheValueSigner->wrap(serialize(...))`; `getMultiple` reads each via `unserialize(cacheValueSigner->verifyAndUnwrap(...))`; `@throws TamperedCacheValueException` on both. `increment()` stays unsigned and untouched.
- **`driverName()` on hand-written ConnectionInterface stubs (F1/F3/F5):** Tier 2 added `ConnectionInterface::driverName(): string`. Any new inline anonymous stub that omits it is abstract-incomplete and fatals. Mitigation: each task that writes a fresh stub must implement `driverName()` (returning e.g. `'sqlite'`); PHPUnit `createMock` auto-stubs it, so only hand-written stubs are affected.
- **Ordering guarantees (F2):** `findMany` returns matched media in unspecified order. Mitigation: `AttachmentManager` reorders results in PHP by the attachment id list and skips misses, and the test asserts the returned order.
- **Interface-change suite breakage (F2/F3):** adding `findMany`/`getPermissionsForRoles` to an interface fatals every implementer that lacks it. Mitigation: each task enumerates the in-repo implementers (media test mocks; `createMockRoleRepo`) and updates them in the same TDD cycle.
- **ChannelInterface is shared (F5):** adding a batch method to `ChannelInterface` breaks `MailChannel`/`SmsChannel`/third-party channels. Mitigation: introduce a separate opt-in `BatchChannelInterface`; sender uses `instanceof` to route.
- **Per-recipient channel resolution (F5):** recipients can declare different channels. Mitigation: sender groups by per-recipient-resolved channel name (not a single shared set); test covers heterogeneous channels.
- **Empty `IN ()` (F3):** an empty role-id list must short-circuit, never building `WHERE rp.role_id IN ()`. Mitigation: explicit guard + test.
- **Cross-plan rebase (Tier 1+2+3 already merged):** F4 must preserve Tier 1's `CacheValueSigner` (above) and not touch Tier 1's `increment()`. F1 touches `findOneBy`/`exists`/`existsBy`/`isColumnUnique` in the SAME `Repository.php` Tier 2 modified for `insertBatch` RETURNING (lines 265-399) — F1's methods are at 232-236 and 541-582, disjoint from insertBatch, so they rebase cleanly. New stubs need Tier 2's `driverName()` (above). Mitigation: tasks declare the file-cluster owners in Context; implementer rebases on latest before starting and re-runs the owning package's full suite. F5 has NO dependency on Tier 2 F9 (UUID PKs).
- **Transaction support (F5/Task 005):** `insertBatch` opens a transaction only when the connection implements `TransactionInterface`. Mitigation: Task 005's batch path follows the same conditional-transaction guard so non-transactional drivers still work. F5's `DatabaseChannel::sendMany` is a single multi-row INSERT and does not require its own transaction.
