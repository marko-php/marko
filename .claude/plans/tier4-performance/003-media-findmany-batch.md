# Task 003: F2 — Batch media hydration in `AttachmentManager::findByAttachable`

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`AttachmentManager::findByAttachable` resolves attachment ids to `Media` entities by looping `mediaRepository->find($id)` once per id — an N+1 query for N attachments. Add a batch lookup method to the media repository contract (`findMany(array): array`) and rewire `findByAttachable` to call it once instead of looping `find()`.

## Description (scope detail)
**`marko/media` ships NO concrete `MediaRepository` — only `MediaRepositoryInterface`.** The interface's implementation is supplied by consuming applications (and by test mocks in this package). Therefore this task's deliverable is: (1) add `findMany(array $ids): array` to `MediaRepositoryInterface` with a documented contract, (2) rewire `AttachmentManager::findByAttachable` to call `findMany` once and re-order/null-skip in PHP, and (3) update every in-repo implementer of the interface — the two test mocks (`AttachmentManagerTest::makeMediaRepository` and `MediaManagerTest`'s mock) — to satisfy the new method, or the entire `marko/media` suite will fatal with "does not implement abstract method". Do NOT invent a concrete `WHERE id IN (...)` SQL repository in this package; the single-query implementation is the consumer's responsibility and is documented in the interface contract.

## Context
- Related files:
  - `packages/media/src/Service/AttachmentManager.php` (`findByAttachable` ~46-63 — the per-id loop; `readonly class`)
  - `packages/media/src/Contracts/MediaRepositoryInterface.php` (only has `save`/`delete`/`find(int $id): ?Media` — add `findMany`; ids are `int`)
  - `packages/media/src/Contracts/MediaAttachmentRepositoryInterface.php` (`findByAttachable` returns `array<int>` ids — these `int` ids are what `findMany` receives)
  - `packages/media/tests/Service/AttachmentManagerTest.php` (`makeMediaRepository` anonymous-class mock at line 76 implementing `MediaRepositoryInterface` — MUST add `findMany`)
  - `packages/media/tests/Service/MediaManagerTest.php` (`makeRepository` at line 244, second anonymous-class mock implementing `MediaRepositoryInterface` — MUST add `findMany`)
- Patterns to follow:
  - Add `MediaRepositoryInterface::findMany(array $ids): array` (`@param array<int> $ids`) returning `array<Media>`, documented: empty input -> empty array with no query; the contract returns a flat `array<Media>` of the matched rows in unspecified order (the caller — `AttachmentManager` — is responsible for any ordering). Confirmed: there is NO concrete `MediaRepository` class in-package and exactly two in-repo implementers (the two test mocks above) — both must add the method or the `marko/media` suite fatals.
  - The mock `findMany` returns the stored media for each requested id that exists, skipping misses (mirrors `find()` returning null).
  - `AttachmentManager::findByAttachable` calls `findMany($mediaIds)` once, then re-orders the returned media to match the attachment id list and skips ids with no matching media (preserve current null-skip + ordering behavior) — the re-ordering/skip logic lives in `AttachmentManager`, not the repository.
  - Query-count assertions: the mock records how many times `findMany` (vs `find`) is invoked; assert `find` is never called and `findMany` is called exactly once regardless of attachment count.

## Requirements (Test Descriptions)
- [ ] `it returns the media entities for all attached ids`
- [ ] `it returns media in the order of the attachment id list`
- [ ] `it skips ids that have no matching media row`
- [ ] `it returns an empty array when there are no attachments`
- [ ] `it resolves all attachments with a single findMany call and never calls find per id`
- [ ] `it defines findMany on MediaRepositoryInterface returning an array of Media`
- [ ] `it does not invoke findMany when there are no attachment ids`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
