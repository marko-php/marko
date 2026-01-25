# Plan: Blog Module Frontend Expansion

## Created
2025-01-25

## Status
in_progress

## Objective
Expand the marko/blog module with WordPress-like frontend functionality including posts with statuses, authors, categories, tags, threaded comments with email verification, pagination, search, and proper SEO support.

## Scope

### In Scope
- Author entity (name, email, bio, slug) with archive pages
- Category entity (hierarchical) with archive pages
- Tag entity (flat taxonomy) with archive pages
- Post expansion: status (draft/published/scheduled), scheduled_at, summary, author relationship
- Threaded comments (5 levels default, configurable) with email verification
- Cookie-based auto-approval for verified commenters
- Honeypot spam prevention and rate limiting
- Pagination (10 per page, numbered with prev/next)
- Search by post title and summary
- Post lifecycle events (created, updated, published, scheduled, deleted)
- Comment lifecycle events (created, verified, deleted)
- SEO: canonical URLs, meta descriptions, pagination links
- CLI command for blog housekeeping (tokens cleanup)
- CLI command for publishing scheduled posts (cron)
- Database migrations for all tables
- Email template for comment verification
- Configurable route prefix (default: /blog)
- Configurable cookie name for browser verification

### Out of Scope
- Admin/backend interface (future phase)
- RSS/Atom feeds (separate `blog-feed` package)
- Sitemap generation (separate `blog-sitemap` package)
- Media/image attachments
- Revision history
- Custom post types (future extension)
- CAPTCHA integration (future extension)
- Pages (static content)

## Success Criteria
- [ ] All entities created with proper attributes and relationships
- [ ] All entities have interfaces for Preference swapping
- [ ] Posts support draft/published/scheduled workflow
- [ ] Comments work with email verification and cookie auto-approval
- [ ] All archive pages (author, category, tag) functional with pagination
- [ ] Search returns relevant results sorted by relevance
- [ ] All lifecycle events dispatched appropriately
- [ ] CLI commands work correctly (cleanup, publish-scheduled)
- [ ] Database migrations create all required tables
- [ ] All tests passing with minimum 80% coverage
- [ ] Code follows Marko standards:
  - `declare(strict_types=1)` in every file
  - Constructor property promotion always
  - No magic methods (`__get`, `__set`, `__call`)
  - No traits (use composition)
  - Type declarations on all parameters, returns, properties
  - Multiline method signatures (params on own lines)
  - `#[\NoDiscard]` on methods where return value matters
  - No `final` classes (blocks Preferences)

## Prerequisites

| Prereq | Description | Package | Status |
|--------|-------------|---------|--------|
| 000 | RoutePrefix Attribute Support | marko/routing | pending |

See `000-routing-prerequisite.md` for full details on implementing configurable route prefixes in `marko/routing`.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Blog Configuration | - | completed |
| 002 | Slug Generator Service | - | completed |
| 003 | Author Entity and Repository | 002 | completed |
| 004 | Category Entity and Repository | 002 | completed |
| 005 | Tag Entity and Repository | 002 | completed |
| 006 | Post Status Enum | - | completed |
| 007 | Expand Post Entity | 002, 003, 006 | completed |
| 008 | Post-Category Relationship | 004, 007 | completed |
| 009 | Post-Tag Relationship | 005, 007 | completed |
| 010 | Comment Status Enum | - | completed |
| 011 | Comment Entity | 007, 010 | completed |
| 012 | Verification Token Entity | - | completed |
| 013 | Comment Repository | 011 | completed |
| 014 | Comment Verification Service | 012, 013 | completed |
| 015 | Comment Rate Limiter | 013 | completed |
| 016 | Honeypot Validation | - | completed |
| 017 | Pagination Service | 001 | completed |
| 018 | Search Service | 007 | completed |
| 019 | Post Lifecycle Events | 007 | completed |
| 020 | Comment Lifecycle Events | 011 | completed |
| 021 | Author Lifecycle Events | 003 | completed |
| 022 | Category Lifecycle Events | 004 | completed |
| 023 | Tag Lifecycle Events | 005 | completed |
| 024 | Post List Controller | 007, 017, 019 | completed |
| 025 | Post Show Controller | 007, 013 | completed |
| 026 | Author Archive Controller | 003, 017 | completed |
| 027 | Category Archive Controller | 004, 017 | completed |
| 028 | Tag Archive Controller | 005, 017 | completed |
| 029 | Comment Submit Controller | 014, 015, 016, 020 | pending |
| 030 | Comment Verify Controller | 014 | pending |
| 031 | Search Controller | 017, 018 | completed |
| 032 | Pagination View Component | 017 | completed |
| 033 | Post List View | 024, 032 | completed |
| 034 | Post Show View | 025 | pending |
| 035 | Comment Thread Component | 013 | completed |
| 036 | Comment Form Component | 016 | completed |
| 037 | Post Show with Comments | 034, 035, 036 | pending |
| 038 | Author Archive View | 026, 032 | completed |
| 039 | Category Archive View | 027, 032 | completed |
| 040 | Tag Archive View | 028, 032 | completed |
| 041 | Search Results View | 031, 032, 046 | completed |
| 042 | Blog Cleanup Command | 012, 015 | pending |
| 043 | SEO Meta Service | 007 | completed |
| 044 | SEO View Integration | 033, 037, 038, 039, 040, 041, 043 | pending |
| 045 | Publish Scheduled Posts Command | 007, 019 | completed |
| 046 | Search Bar Component | - | completed |
| 047 | Module Bindings Configuration | 001, 002, 003, 004, 005, 013, 014, 015, 016, 017, 018, 043 | pending |
| 048 | Module README Documentation | 047 | pending |
| 049 | Database Migrations | 003, 004, 005, 007, 008, 009, 011, 012 | completed |
| 050 | Verification Email Template | 014 | pending |

## Architecture Notes

### Entity Relationships
```
Author (1) ──────< (N) Post
Post   (N) >────< (N) Category  [via PostCategory pivot]
Post   (N) >────< (N) Tag       [via PostTag pivot]
Post   (1) ──────< (N) Comment
Comment(1) ──────< (N) Comment  [self-referential for threading]
```

### Slug Uniqueness Scope

**Slugs are unique per entity type, not globally unique.**

This allows the same slug to exist across different entity types:
- `/blog/author/john` (author with slug "john")
- `/blog/category/john` (category with slug "john")
- `/blog/tag/john` (tag with slug "john")
- `/blog/john` (post with slug "john")

All four can coexist because they're in different URL namespaces.

### Post Status Flow
```
Draft ──► Published
  │           │
  └──► Scheduled ──► Published (via blog:publish-scheduled cron)
```

### Comment Verification Flow
```
Submit ──► Pending ──► Email Sent ──► Link Clicked ──► Verified + Cookie Set
                                            │
Future Comments ◄── Cookie Valid ◄──────────┘ (auto-approved)
```

### URL Structure (with configurable prefix)
- `{prefix}/` - Post list (paginated)
- `{prefix}/{slug}` - Single post with comments
- `{prefix}/author/{slug}` - Author archive
- `{prefix}/category/{slug}` - Category archive
- `{prefix}/tag/{slug}` - Tag archive
- `{prefix}/search?q=term` - Search results
- `{prefix}/{slug}/comment` - POST comment submission
- `{prefix}/comment/verify/{token}` - Verify comment email

Default prefix: `/blog`

### Route Prefix Configuration

Since PHP attributes require constant expressions, configurable route prefixes use a controller-level `#[RoutePrefix]` attribute:

```php
#[RoutePrefix(configKey: 'blog.route_prefix', default: '/blog')]
class PostController
{
    #[Get('/')]           // Resolved to {prefix}/ at registration
    #[Get('/{slug}')]     // Resolved to {prefix}/{slug} at registration
}
```

The `marko/routing` package reads the `#[RoutePrefix]` attribute during route discovery, resolves the config value via `ConfigRepositoryInterface`, and registers routes with the resolved prefix.

**Extensibility Note:** Controllers remain fully swappable via `#[Preference]` regardless of route prefix. Plugins hook methods by class+method name, not by URL.

**Prerequisite:** The `marko/routing` package must support the `#[RoutePrefix]` attribute with config key resolution.

### Module Dependencies (composer.json)
```json
{
    "require": {
        "marko/core": "^1.0",
        "marko/routing": "^1.0",
        "marko/database": "^1.0",
        "marko/view": "^1.0",
        "marko/cache": "^1.0",
        "marko/mail": "^1.0",
        "marko/config": "^1.0",
        "marko/session": "^1.0"
    },
    "suggest": {
        "marko/view-latte": "Required for default blog templates",
        "marko/csrf": "Recommended for CSRF protection on comment forms"
    }
}
```

**Note:** Blog depends on interfaces, NOT specific implementations:
- `marko/view` interface (not `marko/view-latte`) - allows any view engine
- `marko/cache` interface (not `marko/cache-file`) - allows any cache backend
- `marko/session` interface - required for flash messages after comment verification
- `marko/config` - required for loading `config/blog.php` configuration
- `marko/csrf` is **suggested** (not required) - CSRF protection is optional but recommended

### Configuration (config/blog.php)
```php
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

### Extensibility Points

**Interface-Based Architecture (Preference Swapping):**
- All entities: PostInterface, AuthorInterface, CategoryInterface, TagInterface, CommentInterface, VerificationTokenInterface
- All repositories: PostRepositoryInterface, AuthorRepositoryInterface, CategoryRepositoryInterface, TagRepositoryInterface, CommentRepositoryInterface, VerificationTokenRepositoryInterface
- All services: SlugGeneratorInterface, PaginationServiceInterface, SearchServiceInterface, CommentVerificationServiceInterface, CommentRateLimiterInterface, HoneypotValidatorInterface, SeoMetaServiceInterface
- Configuration: BlogConfigInterface (wraps ConfigRepositoryInterface from marko/config)
- Controllers: All swappable via Preferences, all inject interfaces (not concrete classes)

**Dependency Injection:**
- All dependencies injected via constructor (interfaces, not implementations)
- Cache uses CacheInterface - works with any cache backend (file, Redis, etc.)
- No hard-coded dependencies on specific drivers

**Plugin Hooks:**
- All public methods on services/repositories can be intercepted via #[Before]/#[After]
- Controllers methods hookable for request/response modification

**Events:**
- Post lifecycle: PostCreated, PostUpdated, PostPublished, PostScheduled, PostDeleted
- Comment lifecycle: CommentCreated, CommentVerified, CommentDeleted
- Taxonomy lifecycle: Author/Category/Tag Created/Updated/Deleted

**Module Bindings (module.php):**
- Central wiring of interfaces to implementations
- Third parties override via Preferences in their own modules

**View Template Overrides (via marko/view):**
- Already implemented in core - app templates automatically override vendor templates
- `app/myblog/resources/views/blog/post/show.latte` overrides `vendor/marko/blog/resources/views/post/show.latte`
- No PHP changes needed for template customization
- Uses `ModuleTemplateResolver` with app → modules → vendor priority chain

### Deletion Behavior (Cascade/Orphan Handling)

| Entity | On Delete | Behavior |
|--------|-----------|----------|
| Author | BLOCKED | Cannot delete while posts exist. Throws `AuthorHasPostsException`. |
| Category | BLOCKED | Cannot delete while children or posts exist. Throws `CategoryHasChildrenException` or `CategoryHasPostsException`. |
| Tag | BLOCKED | Cannot delete while posts exist. Throws `TagHasPostsException`. |
| Post | CASCADE | Comments and pivot relationships deleted automatically. |
| Comment | CASCADE | Child comments deleted automatically. |

Application-level enforcement prevents orphaned content while maintaining data integrity.

## Risks & Mitigations
- **Email deliverability**: Comment verification depends on email delivery. Mitigation: Clear error messages, resend option.
- **Comment spam**: Honeypot + rate limiting may not catch all spam. Mitigation: Events allow adding more protection via observers.
- **Search performance**: Simple LIKE search may be slow at scale. Mitigation: Interface allows swapping to full-text search later.
- **Route prefix feature**: Requires marko/routing support for `#[RoutePrefix]` attribute. Mitigation: Can be implemented as part of this work or as prerequisite.
