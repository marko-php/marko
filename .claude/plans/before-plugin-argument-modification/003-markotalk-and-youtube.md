# Task 003: Update MarkoTalk and YouTube Script

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Evaluate MarkoTalk's MarkdownPlugin for argument modification (likely no change needed — mutation in place is appropriate for entity objects). Update the YouTube script at ~/Desktop/YOUTUBE.md to mention argument modification as a before plugin capability.

## Context

**NOTE**: Both files in this task are outside the marko repository. They won't be covered by the project's test/lint pipeline. The YOUTUBE.md file is at `~/Desktop/YOUTUBE.md` (not in any git repo). The MarkoTalk file is in a separate project at `~/Sites/markotalk/`.

### MarkoTalk MarkdownPlugin
- File: `~/Sites/markotalk/app/message/src/Plugin/MarkdownPlugin.php`
- Current behavior: Mutates the `Message` entity in-place (`$message->bodyHtml = ...`) and returns null
- This is actually the right pattern for entities — argument modification (returning an array) would mean creating a new Message object, which is heavier and less natural
- **Decision: Leave MarkdownPlugin as-is.** In-place mutation is appropriate here. Note this in implementation notes.

### YouTube Script
- File: `~/Desktop/YOUTUBE.md`
- The plugins section (around line 141) currently describes Before/After and short-circuiting
- Add a brief mention that before plugins can also modify arguments by returning an array
- Keep it conversational and brief — this is a video script, not documentation

## Requirements (Test Descriptions)
- [ ] `it confirms MarkoTalk MarkdownPlugin does not need changes for argument modification`
- [ ] `it updates YouTube script plugin section to mention argument modification via array return`

## Acceptance Criteria
- MarkoTalk still works correctly (no code changes expected)
- YouTube script mentions the three before plugin behaviors naturally within the existing flow
- YouTube script stays conversational and doesn't over-explain

## Implementation Notes
(Left blank - filled in by programmer during implementation)
