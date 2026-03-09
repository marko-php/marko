# Task 002: Clean Up TokenRepository

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove TokenRepository's completely pointless constructor override (same params as parent), and its pass-through `save()` and `delete()` methods that add no behavior.

## Context
- Related files: `packages/blog/src/Repositories/TokenRepository.php`, `packages/blog/tests/Repositories/` (if any token tests exist)
- TokenRepository's constructor repeats parent params with zero additions
- `save(VerificationToken|Entity $token)` just calls `parent::save($token)` — pure pass-through
- `delete(VerificationToken|Entity $token)` just calls `parent::delete($token)` — pure pass-through
- The union type `VerificationToken|Entity` on save/delete is redundant since VerificationToken extends Entity
- Keep everything else: `findByToken()`, `findByCommentId()`, `findBrowserTokenForEmail()`, `deleteExpiredEmailTokens()`, `deleteExpiredBrowserTokens()`

## Requirements (Test Descriptions)
- [ ] `it saves a verification token without constructor override`
- [ ] `it deletes a verification token without method override`
- [ ] `it finds a token by token string`
- [ ] `it finds a token by comment id`
- [ ] `it finds a browser token by email`

## Acceptance Criteria
- All requirements have passing tests
- TokenRepository has no constructor
- TokenRepository has no save() override
- TokenRepository has no delete() override
- Existing TokenRepository tests still pass
