# Task 007: Write blog README

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
The blog package README is missing most sections that the comprehensive Documentation/ReadmeTest expects. This is the most content-heavy README — needs title update, installation details, view driver docs, template overriding, configuration options, extensibility (preferences/plugins/observers), events listing, routes listing, and CLI commands.

## Context
- Related files:
  - `packages/blog/README.md` — current README (has title, installation, quick example, docs link)
  - `packages/blog/tests/Documentation/ReadmeTest.php` — test expectations (very detailed)
  - `packages/blog/src/` — source code
  - Has docs page at `docs/src/content/docs/packages/blog.md`
- **IMPORTANT:** The test expects `# Marko Blog` (title case), but all other packages use `# marko/{name}`. For consistency, update the TEST to expect `# marko/blog` instead, and keep the README title as `# marko/blog`.
- Test checks for specific content (regex patterns and string matches):
  - Title: update test to expect `# marko/blog` (consistent with all other packages), with one-liner matching `/WordPress-like blog.*posts.*authors.*categories.*tags.*comments/i`
  - Installation: `composer require marko/blog`
  - View driver: mentions `marko/view-latte` requirement
  - `### Overriding Templates` section explaining app module overrides
  - Alternative view engines (blade, twig)
  - `## Configuration` with: posts_per_page, comment_max_depth, comment_rate_limit_seconds, verification_token_expiry_days, route_prefix
  - Preference/swap: `#[Preference` attribute usage
  - Plugins: `#[Plugin` with `#[Before]` and `#[After]` attributes
  - Observers: `#[Observer` for event reactions
  - `## Available Events`: PostCreated, PostUpdated, PostPublished, PostDeleted, CommentCreated, CommentVerified, CommentDeleted, CategoryCreated, TagCreated, AuthorCreated
  - `## Routes`: GET /blog, GET /blog/{slug}, GET /blog/category/{slug}, GET /blog/tag/{slug}, GET /blog/author/{slug}, GET /blog/search, POST /blog/{slug}/comment, GET /blog/comment/verify/{token}
  - `## CLI Commands`: blog:publish-scheduled, blog:cleanup

## Requirements (Actual Test Names from ReadmeTest.php)
These are the actual `it()` block names in `packages/blog/tests/Documentation/ReadmeTest.php`. Tests 2 and 3 likely already pass with current README; the rest need new content.
- [ ] `it has title and one-liner describing the module` — update the TEST to expect `# marko/blog` instead of `# Marko Blog` for consistency with all other packages. Write the README title as `# marko/blog` with a one-liner matching the WordPress-like blog regex.
- [ ] `it has installation section with composer command` — `## Installation` + `composer require marko/blog` (likely already passes)
- [ ] `it documents view driver requirement and suggests marko/view-latte` — `marko/view-latte` + regex `/view.*driver|template.*engine/i` (likely already passes)
- [ ] `it explains how to override view templates in app module` — `### Overriding Templates` + regex `/app\/|app module/i`
- [ ] `it explains how to use alternative view engines` — regex `/alternative.*view|different.*engine|blade|twig/i`
- [ ] `it documents all configuration options with defaults` — `## Configuration` + all 5 config keys
- [ ] `it shows how to swap implementations via Preferences` — regex for Preference + regex for swap/replace/override
- [ ] `it shows how to hook methods via Plugins` — `#[Plugin` + regex `/#\[Before\]|#\[After\]/`
- [ ] `it shows how to react to events via Observers` — `#[Observer` + regex `/react.*event|event.*observer/i`
- [ ] `it lists all available lifecycle events` — `## Available Events` + all 10 event class names
- [ ] `it documents all public routes` — `## Routes` + all 8 route strings (exact format: `GET /blog`, `GET /blog/{slug}`, etc.)
- [ ] `it includes CLI commands section` — `## CLI Commands` + `blog:publish-scheduled` + `blog:cleanup`

## Acceptance Criteria
- All 12 tests in `packages/blog/tests/Documentation/ReadmeTest.php` pass (10 newly fixed + 2 already passing)
- README content is accurate to actual blog package source code
- Existing passing tests continue to pass
