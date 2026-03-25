---
title: marko/blog
description: WordPress-like blog functionality for Marko --- posts, authors, categories, tags, and threaded comments with email verification.
---

WordPress-like blog functionality for Marko --- posts, authors, categories, tags, and threaded comments with email verification. Provides a full content management system with routes, view templates, pagination, search, spam prevention, and a rich event system for extending behavior.

## Installation

```bash
composer require marko/blog
```

**Required:** A view driver (e.g., [marko/view-latte](/docs/packages/view-latte/)) and a database driver (e.g., `marko/database-mysql`):

```bash
composer require marko/blog marko/view-latte marko/database-mysql
```

## Quick Start

Once installed with a view and database driver, the blog works automatically:

1. Run migrations to create tables: `marko db:migrate`
2. Visit `/blog` to see the post list
3. Visit `/blog/{slug}` to view a single post

## Configuration

All configuration is optional with sensible defaults. Add to your config:

```php title="config/blog.php"
return [
    'posts_per_page' => 10,              // Posts shown per page
    'comment_max_depth' => 5,            // Maximum reply nesting level
    'comment_rate_limit_seconds' => 30,  // Seconds between comments from same IP/email
    'verification_token_expiry_days' => 7,   // Email token validity
    'verification_cookie_days' => 365,   // Browser token validity after verification
    'verification_cookie_name' => 'blog_verified',
    'route_prefix' => '/blog',           // Must start with /, must not end with /
];
```

## Usage

### View Templates

#### Default Templates (Latte)

The blog includes Latte templates in `resources/views/`:

```
blog::post/index     # Post list with pagination
blog::post/show      # Single post with comments
blog::category/show  # Category archive
blog::tag/index      # Tag archive
blog::author/show    # Author archive
blog::search/index   # Search results
```

#### Using a Different View Engine

The blog uses [marko/view](/docs/packages/view/) interfaces. To use Blade, Twig, or another template engine:

1. Install an alternative view driver instead of `marko/view-latte`
2. Create matching templates in your view driver's expected format
3. The template names (e.g., `blog::post/index`) remain the same

#### Overriding Templates

To customize templates without modifying the package, create matching templates in your app module:

```
app/
  myblog/
    resources/
      views/
        blog/
          post/
            index.latte    # Overrides blog::post/index
            show.latte     # Overrides blog::post/show
```

Templates in `app/` modules take precedence over package templates.

### Security

#### Rate Limiting

Comments are rate-limited per IP address and email. Configure with `comment_rate_limit_seconds` (default: 30 seconds).

#### Honeypot Spam Prevention

The comment form includes a hidden honeypot field. Bots that fill it are silently rejected.

#### Email Verification

First-time commenters receive a verification email. Once verified, a browser token allows future comments without re-verification.

### Extending the Blog

#### Swapping Implementations (Preferences)

Replace any class globally using [`#[Preference]`](/docs/packages/core/). This is how you swap implementations for the entire application:

```php
use Marko\Blog\Repositories\PostRepository;
use Marko\Core\Attributes\Preference;

#[Preference(replaces: PostRepository::class)]
class CustomPostRepository extends PostRepository
{
    public function findPublishedPaginated(
        int $limit,
        int $offset,
    ): array {
        // Custom implementation with caching, filtering, etc.
        return $this->cache->remember('posts', fn () => parent::findPublishedPaginated($limit, $offset));
    }
}
```

All blog module bindings can be overridden:

| Interface | Default Implementation |
|-----------|----------------------|
| `BlogConfigInterface` | `BlogConfig` |
| `PostRepositoryInterface` | `PostRepository` |
| `CommentRepositoryInterface` | `CommentRepository` |
| `CategoryRepositoryInterface` | `CategoryRepository` |
| `TagRepositoryInterface` | `TagRepository` |
| `AuthorRepositoryInterface` | `AuthorRepository` |
| `SlugGeneratorInterface` | `SlugGenerator` |
| `PaginationServiceInterface` | `PaginationService` |
| `SearchServiceInterface` | `SearchService` |
| `HoneypotValidatorInterface` | `HoneypotValidator` |
| `CommentRateLimiterInterface` | `CommentRateLimiter` |
| `CommentVerificationServiceInterface` | `CommentVerificationService` |
| `CommentThreadingServiceInterface` | `CommentThreadingService` |

#### Hooking Methods (Plugins)

Modify method behavior without replacing the entire class using [`#[Plugin]`](/docs/packages/core/):

```php
use Marko\Blog\Controllers\PostController;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Routing\Http\Response;

#[Plugin(target: PostController::class)]
class PostControllerPlugin
{
    #[Before]
    public function show(
        string $slug,
    ): ?string {
        // Redirect old slugs
        if ($slug === 'old-post') {
            return 'new-post';
        }

        return null; // Continue with original slug
    }

    #[After]
    public function index(
        Response $result,
    ): Response {
        // Add cache headers
        return $result->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
```

#### Reacting to Events (Observers)

React to events without modifying the code that triggers them using [`#[Observer]`](/docs/packages/core/):

```php
use Marko\Blog\Events\Post\PostPublished;
use Marko\Core\Attributes\Observer;

#[Observer(event: PostPublished::class)]
class NotifySubscribers
{
    public function __construct(
        private NewsletterService $newsletterService,
    ) {}

    public function handle(
        PostPublished $event,
    ): void {
        $post = $event->getPost();
        $this->newsletterService->sendNewPostNotification($post);
    }
}
```

### Available Events

#### Post Events

| Event | When Dispatched | Data |
|-------|-----------------|------|
| `PostCreated` | New post saved | `getPost()`, `getTimestamp()` |
| `PostUpdated` | Existing post modified | `getPost()`, `getTimestamp()` |
| `PostPublished` | Post status changed to published | `getPost()`, `getPreviousStatus()`, `getTimestamp()` |
| `PostScheduled` | Post scheduled for future publication | `getPost()`, `getPreviousStatus()`, `getTimestamp()` |
| `PostDeleted` | Post removed | `getPost()`, `getTimestamp()` |

#### Comment Events

| Event | When Dispatched | Data |
|-------|-----------------|------|
| `CommentCreated` | New comment submitted | `getComment()`, `getPost()`, `getTimestamp()` |
| `CommentVerified` | Comment verified via email | `getComment()`, `getPost()`, `getVerificationMethod()`, `getTimestamp()` |
| `CommentDeleted` | Comment removed | `getComment()`, `getPost()`, `getTimestamp()` |

#### Taxonomy Events

| Event | When Dispatched | Data |
|-------|-----------------|------|
| `CategoryCreated` | New category created | `getCategory()`, `getTimestamp()` |
| `CategoryUpdated` | Category modified | `getCategory()`, `getTimestamp()` |
| `CategoryDeleted` | Category removed | `getCategory()`, `getTimestamp()` |
| `TagCreated` | New tag created | `getTag()`, `getTimestamp()` |
| `TagUpdated` | Tag modified | `getTag()`, `getTimestamp()` |
| `TagDeleted` | Tag removed | `getTag()`, `getTimestamp()` |
| `AuthorCreated` | New author created | `getAuthor()`, `getTimestamp()` |
| `AuthorUpdated` | Author modified | `getAuthor()`, `getTimestamp()` |
| `AuthorDeleted` | Author removed | `getAuthor()`, `getTimestamp()` |

### Routes

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/blog` | Post list with pagination |
| `GET` | `/blog/{slug}` | Single post with comments |
| `GET` | `/blog/category/{slug}` | Posts in category |
| `GET` | `/blog/tag/{slug}` | Posts with tag |
| `GET` | `/blog/author/{slug}` | Posts by author |
| `GET` | `/blog/search` | Search results (requires `?q=query`) |
| `POST` | `/blog/{slug}/comment` | Submit comment on post |
| `GET` | `/blog/comment/verify/{token}` | Verify comment via email link |

### CLI Commands

#### blog:publish-scheduled

Publishes posts scheduled for the current time. Run via cron every minute:

```bash
marko blog:publish-scheduled
marko blog:publish-scheduled --verbose  # Show each published post
```

#### blog:cleanup

Removes expired verification tokens:

```bash
marko blog:cleanup
marko blog:cleanup --verbose  # Show token counts
```

## API Reference

### PostRepositoryInterface

Extends `RepositoryInterface` (which provides `find()`, `save()`, `delete()`, etc.).

```php
public function findBySlug(string $slug): ?Post;
public function findPublished(): array;
public function findPublishedPaginated(int $limit, int $offset): array;
public function countPublished(): int;
public function findPublishedByCategory(int $categoryId, int $limit, int $offset): array;
public function findPublishedByTag(int $tagId, int $limit, int $offset): array;
public function findPublishedByAuthor(int $authorId, int $limit, int $offset): array;
public function findScheduledPostsDue(): array;
public function getCategoriesForPost(int $postId): array;
public function getTagsForPost(int $postId): array;
```

### CommentRepositoryInterface

Extends `RepositoryInterface` (which provides `find()`, `save()`, `delete()`, etc.).

```php
public function find(int $id): ?Comment;
public function findVerifiedForPost(int $postId): array;
public function findPendingForPost(int $postId): array;
public function countForPost(int $postId): int;
public function countVerifiedForPost(int $postId): int;
public function findByEmail(string $email): array;
```

### CategoryRepositoryInterface

```php
public function findBySlug(string $slug): ?Category;
public function isSlugUnique(string $slug, ?int $excludeId = null): bool;
public function findChildren(Category $parent): array;
public function getPath(Category $category): array;
public function findRoots(): array;
public function getPostsForCategory(int $categoryId): array;
public function getDescendantIds(int $categoryId): array;
```

### TagRepositoryInterface

```php
public function findBySlug(string $slug): ?Tag;
public function findByNameLike(string $name): array;
public function isSlugUnique(string $slug, ?int $excludeId = null): bool;
public function getPostsForTag(int $tagId): array;
```

### AuthorRepositoryInterface

```php
public function findBySlug(string $slug): ?Author;
public function findByEmail(string $email): ?Author;
public function isSlugUnique(string $slug, ?int $excludeId = null): bool;
```

### CommentThreadingServiceInterface

Handles comment tree-building and depth calculation. Injected into controllers that need threaded comment display or reply depth validation.

```php
public function getThreadedComments(int $postId): array;
public function calculateDepth(int $commentId): int;
```

### SearchServiceInterface

```php
public function search(string $query): array;
public function searchPaginated(string $query, int $limit, int $offset): array;
```

`searchPaginated()` returns `array{results: array<SearchResult>, total: int}`.

### PaginationServiceInterface

```php
public function paginate(array $items, int $totalItems, int $currentPage, ?int $perPage = null): PaginatedResult;
public function calculateOffset(int $page, ?int $perPage = null): int;
public function getPerPage(): int;
```

### SlugGeneratorInterface

```php
public function generate(string $title, ?Closure $uniquenessChecker = null): string;
```

### BlogConfigInterface

```php
public function getPostsPerPage(): int;
public function getCommentMaxDepth(): int;
public function getCommentRateLimitSeconds(): int;
public function getVerificationTokenExpiryDays(): int;
public function getVerificationCookieDays(): int;
public function getRoutePrefix(): string;
public function getVerificationCookieName(): string;
public function getSiteName(): string;
```
