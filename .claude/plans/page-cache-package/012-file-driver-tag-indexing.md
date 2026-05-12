# Task 012: FilePageCacheDriver Tag Indexing (purgeTag + reverse-index persistence)

**Status**: completed
**Depends on**: 011
**Retry count**: 0

## Description
Implement tag-based invalidation in `FilePageCacheDriver`. Extend `store()` to write reverse-index entries (one file per tag, listing the page cache keys carrying that tag). Implement `purgeTag()` to read the index, delete each listed page file, then delete the tag file. Use file locking for the read-modify-write of tag files.

## Context
- Related files:
  - `packages/page-cache-file/src/Driver/FilePageCacheDriver.php` (extend the work from task 011)
- Storage layout extension (added by this task):
  - `{config->path()}/tags/{hash('xxh128', $tag)}.tag` — serialized `array<string>` of page cache hashes that carry this tag

## Requirements (Test Descriptions)
- [ ] `it writes a tag index file when storing a response with tags`
- [ ] `it appends a page hash to an existing tag index without duplicating it`
- [ ] `it deletes all pages tagged with a given tag when purgeTag is called`
- [ ] `it deletes the tag index file after purgeTag completes`
- [ ] `it returns true from purgeTag when no tag index file exists for that tag`
- [ ] `it tolerates missing page files referenced by a tag index`
- [ ] `it deletes all tag index files in addition to page files when clear is called`

## Acceptance Criteria
- `store()` writes/updates one `tags/{hash}.tag` file per tag listed in the `CachePolicy`
- Tag-index updates use the **same-file-locking** pattern below — NOT a temp-file rename — because the goal here is mutual exclusion between concurrent appends, not atomic visibility (the tag index is internal). A temp-file + rename loses concurrent writes.
- The page file write itself stays atomic via `*.tmp.{uniqid}` + `rename()`.
- `purgeTag()`:
  - Returns `true` when no tag index exists (idempotent)
  - Acquires `LOCK_EX` on the tag index file before reading
  - Reads the index, deletes each referenced page file (tolerating already-missing files)
  - Truncates + closes the tag index, then `unlink()`s it (release lock by closing the handle before unlink)
- `clear()` is updated to delete `tags/*.tag` in addition to `pages/*.cache`. The `tags/` subdirectory is created on demand on first `store()` with tags.
- Tests in `tests/Unit/Driver/FilePageCacheDriverTagsTest.php` (or extend existing test file)
- All `@throws` documented
- Strict types declared

## Implementation Notes
- **Concurrent-write locking pattern** (read-modify-write under `LOCK_EX`):
  ```php
  $fp = fopen($tagIndexPath, 'cb+');         // create-or-open binary read-write, no truncate
  if ($fp === false) { return false; }
  try {
      flock($fp, LOCK_EX);                    // blocks until exclusive lock
      $existing = stream_get_contents($fp);   // read whatever is in the file (may be '')
      $hashes = $existing !== false && $existing !== ''
          ? unserialize($existing)
          : [];
      $hashes = array_values(array_unique([...$hashes, $newPageHash]));
      ftruncate($fp, 0);
      rewind($fp);
      fwrite($fp, serialize($hashes));
      fflush($fp);
  } finally {
      flock($fp, LOCK_UN);
      fclose($fp);
  }
  ```
- This pattern serializes concurrent PHP-FPM workers writing the same tag file. Workers writing **different** tag files do not contend.
- The tag-index list grows unboundedly until `purgeTag` clears it. Stale page hashes (pointing to expired/deleted pages) accumulate. `purgeTag` tolerates missing referenced page files. Periodic compaction is a future-plan concern (a `page-cache:gc` command).
- For `clear()`: use `glob()` on each subdirectory and unlink. The order does not matter — orphaned tag entries pointing to gone pages are tolerated by `purgeTag`.
- `Marko\Cache\File\Driver\FileCacheDriver` does NOT use `flock` for read-modify-write because it does plain whole-file overwrites with rename. Its model does not apply here. Search the rest of the codebase for any `flock` usage to confirm the wider convention before implementing.
