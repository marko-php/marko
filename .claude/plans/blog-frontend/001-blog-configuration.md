# Task 001: Blog Configuration

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the blog module configuration file with sensible defaults for pagination, comments, verification settings, routing, and cookies. This provides a central place for all configurable blog behavior.

## Context
- Related files: `packages/blog/config/blog.php` (new), `packages/blog/src/Config/BlogConfig.php` (new)
- Patterns to follow: Other Marko packages use `config/` directory with PHP arrays
- Configuration is auto-loaded by `marko/config` via `ConfigDiscovery`
- BlogConfig service wraps `ConfigRepositoryInterface` with typed accessors for blog settings

## Requirements (Test Descriptions)
- [ ] `it provides default posts_per_page value of 10`
- [ ] `it provides default comment_max_depth value of 5`
- [ ] `it provides default comment_rate_limit_seconds value of 30`
- [ ] `it provides default verification_token_expiry_days value of 7`
- [ ] `it provides default verification_cookie_days value of 365`
- [ ] `it provides default route_prefix value of /blog`
- [ ] `it provides default verification_cookie_name value of blog_verified`
- [ ] `it allows configuration values to be overridden`
- [ ] `it validates route_prefix starts with forward slash`
- [ ] `it validates route_prefix does not end with forward slash`

## Configuration Structure

```php
// packages/blog/config/blog.php
return [
    // Pagination
    'posts_per_page' => 10,

    // Comments
    'comment_max_depth' => 5,
    'comment_rate_limit_seconds' => 30,

    // Email Verification
    'verification_token_expiry_days' => 7,
    'verification_cookie_days' => 365,
    'verification_cookie_name' => 'blog_verified',

    // Routing
    'route_prefix' => '/blog',
];
```

## Acceptance Criteria
- All requirements have passing tests
- Configuration file exists at `packages/blog/config/blog.php`
- BlogConfigInterface defined for Preference swapping
- BlogConfig service implements interface, injecting ConfigRepositoryInterface from marko/config
- BlogConfig provides typed accessors:
  - `getPostsPerPage(): int`
  - `getCommentMaxDepth(): int`
  - `getCommentRateLimitSeconds(): int`
  - `getVerificationTokenExpiryDays(): int`
  - `getVerificationCookieDays(): int`
  - `getVerificationCookieName(): string`
  - `getRoutePrefix(): string`
- Allows custom config implementations via Preferences
- Config values can be overridden in app's config/blog.php (higher priority)
- Code follows Marko standards

## Route Prefix Implementation

Since PHP attributes require constant expressions, configurable route prefixes need special handling:

**Approach: Controller-level `#[RoutePrefix]` attribute with config key resolution**

```php
#[RoutePrefix(configKey: 'blog.route_prefix', default: '/blog')]
class PostController
{
    #[Get('/')]           // Resolved to {prefix}/ at registration
    #[Get('/{slug}')]     // Resolved to {prefix}/{slug} at registration
}
```

The `marko/routing` package reads the `#[RoutePrefix]` attribute during route discovery, resolves the config value via `ConfigRepositoryInterface`, and registers routes with the resolved prefix.

**Route structure with default `/blog` prefix:**
- `{prefix}/` → Post list
- `{prefix}/{slug}` → Single post
- `{prefix}/author/{slug}` → Author archive
- `{prefix}/category/{slug}` → Category archive
- `{prefix}/tag/{slug}` → Tag archive
- `{prefix}/search` → Search results
- `{prefix}/{slug}/comment` → Comment submission (POST)
- `{prefix}/comment/verify/{token}` → Email verification

**Extensibility Note:** Controllers remain fully swappable via `#[Preference]` regardless of route prefix. Plugins hook methods by class+method name, not by URL, so `#[Before]` on `PostController::index` works regardless of what URL the method is mounted at.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
