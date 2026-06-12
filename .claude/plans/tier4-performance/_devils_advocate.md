# Devil's Advocate Review: tier4-performance

Reviewed against the CURRENT tree on `feature/tier4-performance` (Tier 1 + 2 + 3 merged). All cited classes/methods were cross-checked against source.

## Critical (Must fix before building)

### C1 — Task 006 (F4) must preserve Tier 1's HMAC value signer, or it breaks existing tests AND silently disables tamper protection
`packages/cache-redis/src/Driver/RedisCacheDriver.php` wraps every stored value through `CacheValueSigner`:
- `set()`: `$this->cacheValueSigner->wrap(serialize($value))`
- `get()`: `unserialize($this->cacheValueSigner->verifyAndUnwrap($data))`
- envelope format is `<64-hex-hmac>.<serialized>` (verified by `RedisCacheDriverTest` lines 428-490).

Task 006's description says `getMultiple` should "unserialize hits" and `setMultiple` should "queue setex/set per pair" — it never mentions the signer. If a worker takes that literally and writes raw `serialize()`/`unserialize()` in the batch paths:
1. The EXISTING behavioral tests `sets multiple keys` (line 373) and `gets multiple keys` (line 351) will FAIL, because `setMultiple(...)` then `$this->driver->get('key1')` round-trips through `get()`, which calls `verifyAndUnwrap()` on a value that was stored WITHOUT an envelope → throws `TamperedCacheValueException`.
2. Even if the batch read also bypassed the signer, it would silently strip tamper protection from the multi-key path — a production-safety regression of a security feature Tier 1 deliberately added.

Fix: `setMultiple` must wrap each value `$this->cacheValueSigner->wrap(serialize($value))` before queueing `setex`/`set` in the pipeline; `getMultiple` must `unserialize($this->cacheValueSigner->verifyAndUnwrap($data))` for each non-null MGET result (and apply `$default` for nulls). `@throws` must include `TamperedCacheValueException` (matching `get()`/`set()`). `increment()` (Tier 1, lines 172-188) is NOT routed through the signer and MUST NOT be touched.

### C2 — Task 006 (F4): the test `MockRedisClient` has no `mget` and no `pipeline`; the existing `del` IS already variadic
`MockRedisClient extends Predis\Client` (`tests/Unit/RedisCacheDriverTest.php` line 19) currently stubs only `get/set/setex/exists/del/keys/ttl/incr/expire`. The task must add `mget(...$keys): array` (returning values in argument order, null for misses) and a `pipeline(callable): array` that invokes the callback with a recorder collecting queued `setex`/`set` calls and applies them to `$storage`/`$ttls`, plus call-count tracking so a single batched invocation can be asserted. Note `del(...$keys)` (lines 71-93) is ALREADY variadic and already flattens array args — `deleteMultiple` can call `del(...$prefixedKeys)` and the mock handles it; do not "add" a variadic del, it exists. The Predis pipeline contract is `pipeline(function ($pipe) {...})` returning an array of results; the mock's recorder must expose `setex`/`set` with the same signatures the driver calls.

## Important (Should fix before building)

### I1 — Tasks 001, 002, 004, 005, 007: every NEW inline anonymous `ConnectionInterface` stub MUST implement `driverName(): string` or the suite fatals
Tier 2 added `ConnectionInterface::driverName(): string` (`packages/database/src/Connection/ConnectionInterface.php` line 62). Any anonymous class `implements ConnectionInterface` that omits it is abstract-incomplete and fatals at instantiation. The existing reference stubs already include it (`RepositoryCrudTest` line 159, `createRoleMockConnectionWithHistory` line 256) returning `'sqlite'`, but the task files don't tell a worker writing a fresh stub to include it. PHPUnit `createMock(ConnectionInterface::class)` auto-stubs it, so only hand-written anonymous stubs are affected (tasks 001/002/004/005/007's query-count stubs). Add an explicit note to each affected task.

### I2 — Tasks 001 & 002: line references are stale; `exists`/`existsBy` currently DELEGATE, which interacts with task ordering
Current line numbers in `Repository.php`: `findOneBy` is 232-236 (task 001 correct), but `exists` is 541-545 (task 002 says ~517-521), `existsBy` is 552-556 (task says ~528-532), `isColumnUnique` is 561-582 (task says ~542-557). More important than the drift: `exists()` delegates to `find()` and `existsBy()` delegates to `findOneBy()` (lines 544, 555). Task 001 changes `findOneBy` to a `LIMIT 1` path; if Task 002's worker leaves `existsBy` delegating to the new `findOneBy`, it still hydrates an entity (the LIMIT-1 path returns a full hydrated+eager-loaded entity) — violating Task 002's "the probe must never construct an entity" requirement. Task 002 must replace both `exists` and `existsBy` bodies with dedicated `SELECT 1 ... LIMIT 1` probes, NOT keep delegating. Update the line refs and make the no-delegation requirement explicit.

### I3 — Task 004 (F3): `getPermissionsForRoles` hydrates Permissions, so it `@throws EntityException`; the description omits this
`getPermissionsForRole` (RoleRepository line 98) declares `@throws EntityException` because `hydrator->hydrate()` throws it. The new `getPermissionsForRoles` mirrors that hydration and must carry the same `@throws EntityException`, which then propagates up through `loadRolesAndPermissions` and `AdminUserProvider::retrieveById`/`retrieveByCredentials`/`retrieveByRememberToken`. The interface method and the provider call chain must declare `@throws EntityException` per code-standards rule 9. (The provider's `retrieveBy*` methods currently declare no `@throws`; adding the propagation is required for lint.)

### I4 — Task 004 (F3): the provider entry point is `retrieveByRememberToken`, not `retrieveByToken`
Both `_plan.md` and Task 004 say `loadRolesAndPermissions` is reached via "`retrieveByToken`". The actual method is `retrieveByRememberToken` (AdminUserProvider line 73). Minor naming, but a worker grepping for `retrieveByToken` will not find it. Correct the references so the worker traces the right call sites (`retrieveById` line 22, `retrieveByCredentials` line 40, `retrieveByRememberToken` line 73).

### I5 — Task 004 (F3): `createMockRoleRepo` is a `readonly class` and already implements `existsBy`; the new method must follow the same shape
The mock at `AdminUserProviderTest.php` line 318 is `new readonly class (...) implements RoleRepositoryInterface` and its `permissionsMap` is keyed by role id (line 324). Task 004 already calls this out, but note the mock also implements `existsBy` (line 356) which is NOT on `RoleRepositoryInterface` directly — it's inherited from `RepositoryInterface`. The worker must add `getPermissionsForRoles(array $roleIds): array` returning the union across the requested ids from `permissionsMap`, deduplicated, and keep the class `readonly`. Confirmed: this is the only in-repo anonymous implementer of the interface besides `RoleRepository` itself.

### I6 — Task 007 (F5): the sender's batch (`sendMany`) dispatch needs the SAME error wrapping the per-recipient path has
`NotificationSender::send` (lines 40-47) wraps each `channel->send()` in `try { } catch (ChannelException) { rethrow } catch (Throwable) { throw NotificationException::sendFailed(...) }`. When F5 routes a group to `channel->sendMany($group, $notification)`, that call must be wrapped in the identical try/catch (ChannelException passes through, other Throwable → `NotificationException::sendFailed($channelName, ...)`). The task says the fallback path preserves wrapping but is silent on the batch path. Also: `$this->manager->channel($channelName)` throws `NotificationException::unknownChannel` — the grouping/dispatch must resolve channels through the manager exactly as today so the existing test `throws NotificationException when notification declares unknown channel` (sender test line 81) stays green.

### I7 — Task 007 (F5): `sendMany` must declare `@throws ChannelException` AND handle `RandomException`/`JsonException` it raises internally
`DatabaseChannel::send` calls `random_bytes()` (→ `RandomException`) via `generateUuid()` and `json_encode(..., JSON_THROW_ON_ERROR)` (→ `JsonException`), both swallowed by its `catch (Throwable)` → `ChannelException::deliveryFailed('database', ...)`. `sendMany` builds N UUIDs and N json_encodes, so it must wrap the whole multi-row build+execute in the same `try/catch (Throwable) { throw ChannelException::deliveryFailed('database', ...) }` and declare `@throws ChannelException`. The task mentions wrapping a failed `execute()` but not the per-row `generateUuid`/`json_encode` failures — make the whole-operation wrapping explicit.

### I8 — Task 003 (F2): `MediaRepositoryInterface::find` takes `int`, so `findMany(array $ids)` ids are `int`; the `MediaManagerTest` mock is at line 244, not ~228
`MediaRepositoryInterface::find(int $id)` (line 19) — ids are `int`. `MediaAttachmentRepositoryInterface::findByAttachable` returns `array<int>` (line 24). So `findMany(array $ids): array` takes `array<int>`. The second in-repo implementer is `MediaManagerTest::makeRepository` at line 244-278 (task says "~line 228" — stale). Both that mock and `AttachmentManagerTest::makeMediaRepository` (line 76) must add `findMany`. Confirmed there are exactly two in-repo implementers and NO concrete `MediaRepository` class — the task's premise holds. Note: `find()` (and therefore the mock's `findMany`) returns entities keyed only by id presence; the contract should document `findMany` returns a flat `array<Media>` of matched rows in unspecified order (AttachmentManager reorders).

## Minor (Nice to address)

### M1 — Task 004: consider adding a `getPermissionsForRoles` signature test to `RoleRepositoryInterfaceTest`
That file has per-method signature tests (e.g. `getPermissionsForRole method signature requires int and returns array`, lines 58-69) in addition to the `$expectedMethods` presence list. Adding a parallel signature test for `getPermissionsForRoles` (param `roleIds`, type `array`, returns `array`) would match the existing convention, though only the `$expectedMethods` addition is strictly required to avoid a fatal.

### M2 — Task 006: the test file is `packages/cache-redis/tests/Unit/RedisCacheDriverTest.php`
Task 006 references `tests/Unit/...RedisCacheDriverTest.php` with an ellipsis; the actual path has no extra subdirectory. Minor, but worth pinning so the worker edits the right file.

### M3 — F5 grouping order vs. existing test expectations
The existing sender tests assert `send` is called the right number of times but not the order across channels. F5's channel-grouping changes iteration from per-notifiable-then-channel to per-channel-then-group. Current tests don't assert cross-channel ordering, so they stay green, but the new heterogeneous-channels test should pin per-recipient routing (already in task requirement `it routes each recipient only to the channels that recipient declared`).

## Questions for the Team

### Q1 — Should the empty-`$values` case of `setMultiple` (F4) still open a pipeline?
An empty `setMultiple([])` opening an empty Predis pipeline is a wasted round-trip. Worth a no-op guard (`if ($values === []) return true;`)? Same question for `getMultiple([])` (skip MGET) and `deleteMultiple([])` (skip DEL). Low stakes, but consistent with the F3 empty-`IN ()` guard philosophy.

### Q2 — Does `findMany` (F2) need a deterministic order in the interface contract, or is PHP-side reordering the permanent answer?
The plan documents `findMany` returns unspecified order and `AttachmentManager` reorders. That's fine, but if other consumers call `findMany` directly they'll get arbitrary order. Acceptable for now; flagging in case a future caller assumes input-order output.

### Q3 — Chunk size constant for F5 multi-row INSERT and F3 batched INSERT
Both Task 005 and Task 007 say "chunk so placeholder count stays within driver limits" without naming a value. Worth fixing a shared, typed constant (e.g. rows-per-chunk) so the two batch paths are consistent and the limit is documented? (pgsql max params 65535; a conservative rows-per-chunk avoids it.)
