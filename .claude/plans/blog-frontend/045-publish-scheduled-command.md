# Task 045: Publish Scheduled Posts Command

**Status**: pending
**Depends on**: 007, 019
**Retry count**: 0

## Description
Create a CLI command to publish posts whose scheduled_at datetime has passed. Intended to run via cron (e.g., every minute) to automatically transition scheduled posts to published status.

## Context
- Related files: `packages/blog/src/Commands/PublishScheduledCommand.php`
- Patterns to follow: Marko CLI command with `#[Command]` attribute
- Command: `blog:publish-scheduled`
- Should be idempotent and safe to run frequently

## Requirements (Test Descriptions)
- [ ] `it is registered as blog:publish-scheduled command`
- [ ] `it finds all posts with status scheduled and scheduled_at in the past`
- [ ] `it changes status to published for matching posts`
- [ ] `it sets published_at to current datetime`
- [ ] `it dispatches PostPublished event for each published post`
- [ ] `it reports count of posts published`
- [ ] `it handles case when no scheduled posts are due`
- [ ] `it provides verbose output option showing post titles`
- [ ] `it returns success exit code on completion`
- [ ] `it is safe to run concurrently without double-publishing`

## Acceptance Criteria
- All requirements have passing tests
- Command registered and discoverable
- Uses PostRepository for queries and updates
- Dispatches events for each state change
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
