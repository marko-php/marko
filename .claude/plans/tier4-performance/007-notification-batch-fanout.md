# Task 007: F5 — Batched database-channel notification fan-out

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`NotificationSender::send` loops every recipient and invokes `channel->send($notifiable, $notification)` once per recipient; `DatabaseChannel::send` runs a single raw INSERT each time. Fanning a notification to N recipients on the `database` channel therefore issues N inserts. Add a channel-level batch path so recipients sharing the `database` channel are persisted via one chunked multi-row INSERT, while non-batchable channels fall back to per-recipient `send`.

## Description (scope detail)
Typically runs on a queue worker (not request-path), so this is a Medium-priority throughput win rather than a latency fix.

**Do NOT add a method to `ChannelInterface`.** That contract is implemented by `MailChannel`, `DatabaseChannel`, the docs `SmsChannel`, and arbitrary third-party channels; adding `sendMany` there is a breaking change that fatals every existing implementer and consumer mock. Instead introduce a **new, separate, opt-in** `BatchChannelInterface` with `sendMany(array $notifiables, NotificationInterface $notification): void`. `DatabaseChannel` additionally implements `BatchChannelInterface`; all other channels are untouched and keep working through `ChannelInterface::send` unchanged.

The sender resolves channels **per recipient** (the current code calls `$notification->channels($notifiable)` for each notifiable, and recipients may declare different channels — see the existing `it resolves channels from notification for each notifiable` test). The batch path must therefore: build a map of channel-name -> list of recipients that declared that channel, then for each channel, if the resolved channel `instanceof BatchChannelInterface` and the group has >1 recipient, call `sendMany($group, $notification)` once; otherwise fall back to `send()` per recipient. Single-recipient and non-batch channels must behave exactly as today.

**This task does NOT depend on Tier 2 F9 (`insertBatch` RETURNING pgsql PK fix).** `notifications` rows use a pre-generated UUID primary key written explicitly in the VALUES list — there is no auto-increment PK, no `lastInsertId()` round-trip, and no `Entity`/`Repository::insertBatch` involvement. Build the multi-row VALUES list directly in `DatabaseChannel` (mirroring only the *placeholder-construction* shape of `insertBatch`, not its PK-assignment logic). The pgsql `lastInsertId` corruption fixed by F9 cannot affect this path.

## Context
- Related files:
  - `packages/notification/src/NotificationSender.php` (`send` ~28-49 — per-recipient channel resolution then per-channel `send`)
  - `packages/notification/src/Channel/DatabaseChannel.php` (`send` — single raw INSERT with id/type/notifiable_type/notifiable_id/data/read_at/created_at + `generateUuid`)
  - `packages/notification/src/Contracts/ChannelInterface.php` (UNCHANGED — do not edit)
  - new `packages/notification/src/Contracts/BatchChannelInterface.php` (add this; `sendMany(array, NotificationInterface): void`, `@throws ChannelException`)
  - `packages/notification/tests/Unit/NotificationSenderTest.php` (existing tests use PHPUnit `createMock(ChannelInterface::class)` — the existing `database`-channel mocks are plain `ChannelInterface`, NOT `BatchChannelInterface`, so they correctly fall back to per-recipient `send()` and stay green; register a real `DatabaseChannel` or a `BatchChannelInterface` mock for the new batch test) and `tests/Unit/Channel/DatabaseChannelTest.php` (uses PHPUnit `createMock(ConnectionInterface::class)`; the new batch query-count test needs a hand-written anonymous stub instead, which must implement `driverName()`)
  - `packages/database/src/Repository/Repository.php` `insertBatch` (~314-323 placeholder/multi-row VALUES construction shape only — NOT the PK loop)
- Patterns to follow:
  - `DatabaseChannel::sendMany`: build one multi-row `INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at) VALUES (?,?,?,?,?,?,?),(...)` over all recipients, chunked so the placeholder count stays within driver limits (use a typed rows-per-chunk constant); each row carries the same column data the single-send path writes (fresh UUID per row via `generateUuid`, `$notification::class`, `(string) $notifiable->getNotifiableId()`, `$notifiable->getNotifiableType()`, `json_encode($notification->toDatabase($notifiable), JSON_THROW_ON_ERROR)` per recipient, null `read_at`, `date('Y-m-d H:i:s')`). `generateUuid()` calls `random_bytes()` (`RandomException`) and the json_encode throws `JsonException` — wrap the ENTIRE per-chunk build+execute in `try { ... } catch (Throwable $e) { throw ChannelException::deliveryFailed('database', $e->getMessage()); }` exactly as `send()` does, so UUID/JSON failures are wrapped too. Declare `@throws ChannelException` on `sendMany`.
  - The sender groups recipients by their per-recipient-resolved channel name (resolved via `$this->manager->channel($channelName)`, which throws `NotificationException::unknownChannel` — keep that resolution so the existing unknown-channel test stays green). For each channel: if the resolved channel `instanceof BatchChannelInterface` and the group has >1 recipient, call `sendMany($group, $notification)`, ELSE call `send()` per recipient. BOTH the `sendMany` call and the per-recipient `send` calls MUST be wrapped in the SAME try/catch the current `send` loop uses (lines 40-47): `catch (ChannelException) { rethrow }` / `catch (Throwable) { throw NotificationException::sendFailed($channelName, ...) }`. Do not let the batch path skip this wrapping.
  - Query-count assertions: a NEW anonymous `ConnectionInterface` stub recording each `execute()` (it MUST implement `driverName(): string` — Tier 2 interface addition — or it fatals; the existing `DatabaseChannelTest` uses PHPUnit `createMock` which auto-stubs `driverName`, but a hand-written stub does not). Assert the number of INSERT statements equals the chunk count (one when N fits a single chunk), that N rows' worth of bindings are present, and each row's UUID is distinct.

## Requirements (Test Descriptions)
- [ ] `it persists a notification for every recipient on the database channel`
- [ ] `it issues a single multi-row insert when all recipients fit one chunk`
- [ ] `it issues one insert per chunk when recipients exceed the chunk size`
- [ ] `it writes the same column data per row as the single-recipient send`
- [ ] `it generates a distinct id for each persisted notification row`
- [ ] `it falls back to per-recipient send for channels without batch support`
- [ ] `it routes each recipient only to the channels that recipient declared`
- [ ] `it wraps a batch insert failure in a channel exception`
- [ ] `it leaves MailChannel and other ChannelInterface implementations unchanged`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
