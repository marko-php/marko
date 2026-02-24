# Task 005: Refactor blog controller/service/command tests to use marko/testing

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace Session, Mailer, and EventDispatcher stubs in blog controller, service, and command tests. Delete MockSession.php from Mocks directory. Blog's MockCookieJar stays (implements blog-specific CookieJarInterface, not authentication's).

## Context
- Related files:
  - `packages/blog/tests/Mocks/MockSession.php` — DELETE (88 lines)
  - `packages/blog/tests/Controllers/CommentVerifyControllerTest.php` — replace MockSession (lines 368-443) with FakeSession
  - `packages/blog/tests/Controllers/PostControllerTest.php` — replace anonymous SessionInterface (line 1490) with FakeSession
  - `packages/blog/tests/Controllers/CommentControllerTest.php` — replace EventDispatcher helper (line 1136) with FakeEventDispatcher
  - `packages/blog/tests/Services/CommentVerificationServiceTest.php` — replace inline MockMailer (lines 620-639) and MockEventDispatcher (lines 690-700)
  - `packages/blog/tests/Commands/PublishScheduledCommandTest.php` — replace StubEventDispatcher (line 316) with FakeEventDispatcher
  - `packages/blog/composer.json` — add marko/testing to require-dev

### What Stays (domain-specific)
- `tests/Mocks/MockPostRepository.php`
- `tests/Mocks/MockCategoryRepository.php`
- `tests/Mocks/MockCommentRepository.php`
- `tests/Mocks/MockAuthorRepository.php`
- `tests/Controllers/CommentVerifyControllerTest.php` → MockCookieJar stays (blog CookieJarInterface ≠ authentication CookieJarInterface)
- `tests/Services/CommentVerificationServiceTest.php` → MockBlogConfig, MockTokenRepository, MockCommentRepository stay

### Key Property Changes
| Old | New |
|---|---|
| `MockSession::$flashMessages` | `FakeSession::flash()` |
| `MockSession::$startCalled` | `FakeSession::$started` |
| `MockMailer::$sentMessages` | `FakeMailer::$sent` |
| `MockEventDispatcher::$dispatchedEvents` | `FakeEventDispatcher::$dispatched` |
| `StubEventDispatcher::$dispatchedEvents` | `FakeEventDispatcher::$dispatched` |

## Requirements (Test Descriptions)
- [ ] `it uses FakeSession instead of MockSession in CommentVerifyControllerTest`
- [ ] `it uses FakeSession instead of anonymous session in PostControllerTest`
- [ ] `it uses FakeEventDispatcher instead of helper in CommentControllerTest`
- [ ] `it uses FakeMailer and FakeEventDispatcher in CommentVerificationServiceTest`
- [ ] `it uses FakeEventDispatcher instead of StubEventDispatcher in PublishScheduledCommandTest`
- [ ] `it deletes MockSession.php from Mocks directory`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing blog controller/service/command tests pass unchanged
- `packages/blog/tests/Mocks/MockSession.php` deleted
- Inline/named stubs for Session, Mailer, EventDispatcher removed
- MockCookieJar and domain-specific mocks untouched
- `marko/testing` added to `packages/blog/composer.json` require-dev
- Run: `./vendor/bin/pest packages/blog/tests/ --parallel`
