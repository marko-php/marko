# Task 013: docs-markdown rejects path-traversal page IDs

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`MarkdownRepository::getRawMarkdown(string $id)` builds the file path by concatenating the configured docs path with the caller-supplied `$id` and a `.md` suffix, with NO containment check. A `../`-laden id (e.g. `../../../../etc/passwd%00` style, or simply `../../composer`) resolves outside the docs directory and reads any `.md` file on disk — a path-traversal / arbitrary-file-read defect. Harden it: resolve the candidate path with `realpath()` and verify it stays under `realpath($docsPath)` before reading; reject loudly with a `DocsMarkdownException`.

## Context
- Related files:
  - `packages/docs-markdown/src/MarkdownRepository.php` (`getRawMarkdown` lines 43-53 — builds `$this->docsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $id) . '.md'` at line 46, `file_exists` guard at 48-50, read at 52; `listAllPages` 24-41 is unaffected)
  - `packages/docs-markdown/src/Exceptions/DocsMarkdownException.php` (single factory `pageNotFound(string $id, string $docsPath)` — add a new traversal factory here, mirroring its message/context/suggestion shape)
- Patterns to follow:
  - Loud errors: add a static factory `DocsMarkdownException::pathTraversal(string $id, string $docsPath)` (or similarly named) with `message`/`context`/`suggestion` naming the rejected id and the docs root. Do NOT silently fall back to `pageNotFound` for traversal — the failure mode must be distinct and explicit.
  - Containment check: `realpath()` the resolved candidate, `realpath()` the docs root, and confirm the resolved candidate is the root itself or begins with `root . DIRECTORY_SEPARATOR`. `realpath()` returns `false` for a nonexistent path — preserve the existing not-found behavior for legitimate-but-missing ids (they must still throw `pageNotFound`, NOT the traversal exception). Decide order carefully: a `../`-containing id that resolves to a real file outside the root must hit the traversal path; a clean id pointing at a missing file must hit `pageNotFound`.
  - `@throws DocsMarkdownException` already documented on the method — keep it (it now covers both factories).

### Verification note (read at planning time)
Confirmed against source: line 46 concatenation has no `realpath`/containment; `file_exists` (48) is the only guard. `DocsMarkdownException` currently has exactly one factory (`pageNotFound`). Namespace `Marko\DocsMarkdown`; package `marko/docs-markdown`.

## Requirements (Test Descriptions)
- [ ] `it reads markdown for a legitimate page id`
- [ ] `it throws DocsMarkdownException for an id containing path-traversal segments`
- [ ] `it does not read a .md file outside the docs directory via a traversal id`
- [ ] `it still throws pageNotFound for a clean id that does not exist`
- [ ] `it populates message, context, and suggestion on the traversal exception`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
