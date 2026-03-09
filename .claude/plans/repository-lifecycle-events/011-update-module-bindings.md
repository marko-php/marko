# Task 011: Update Blog module.php Bindings for CommentThreadingService

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Add the CommentThreadingServiceInterface → CommentThreadingService binding to the blog module.php so the DI container can resolve it.

## Context
- Related files: `packages/blog/module.php`
- New binding: `CommentThreadingServiceInterface::class => CommentThreadingService::class`
- Existing bindings are already in the file — just add the new one

## Requirements (Test Descriptions)
- [ ] `it binds CommentThreadingServiceInterface to CommentThreadingService`
- [ ] `it preserves all existing bindings`

## Acceptance Criteria
- All requirements have passing tests
- module.php includes the new binding
- No existing bindings removed or modified
- Module bindings test passes (if one exists at `packages/blog/tests/Module/ModuleBindingsTest.php`)
