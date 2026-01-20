# Task 006: CodeSnippetExtractor

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a utility class that extracts code snippets from source files for display in error reports. This provides context by showing the lines of code surrounding where an error occurred.

## Context
- Related files: `packages/errors-simple/src/CodeSnippetExtractor.php`
- Must be fault-tolerant - gracefully handle unreadable files
- Should limit memory usage by not loading entire files

## Requirements (Test Descriptions)
- [ ] `it extracts lines around a given line number`
- [ ] `it returns configurable number of context lines before and after`
- [ ] `it defaults to 5 lines of context on each side`
- [ ] `it handles line numbers near start of file`
- [ ] `it handles line numbers near end of file`
- [ ] `it returns empty array when file does not exist`
- [ ] `it returns empty array when file is not readable`
- [ ] `it preserves original line numbers as array keys`
- [ ] `it highlights the error line in returned data`
- [ ] `it handles files with fewer lines than context window`
- [ ] `it trims trailing whitespace but preserves indentation`

## Acceptance Criteria
- All requirements have passing tests
- Never throws exceptions - always returns gracefully
- Code follows project standards
