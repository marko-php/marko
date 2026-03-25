# marko/blog

WordPress-like blog for Marko — posts, authors, categories, tags, and threaded comments with email verification.

## Installation

```bash
composer require marko/blog
```

**Required:** A view driver (e.g., `marko/view-latte`) and a database driver (e.g., `marko/database-mysql`):

```bash
composer require marko/blog marko/view-latte marko/database-mysql
```

## Quick Example

Once installed with a view and database driver, the blog works automatically:

1. Run migrations to create tables: `marko db:migrate`
2. Visit `/blog` to see the post list
3. Visit `/blog/{slug}` to view a single post

## View Templates

The blog package ships with Latte templates (via `marko/view-latte`) but supports any template engine.

### Overriding Templates

You can override any blog template in your app module by creating a matching path under `app/views/blog/`. The app module's view path takes precedence, allowing full control over rendering without modifying the package.

For example, to override the post list template:

```
app/views/blog/post/list.latte
```

### Alternative View Engines

To use a different template engine such as Blade or Twig, install the corresponding view driver package and register your own view implementations via Preferences. Any view driver implementing the view contract works.

## Configuration

Publish or set the following keys in your config:

| Key | Default | Description |
|-----|---------|-------------|
| `posts_per_page` | `10` | Number of posts shown per page |
| `comment_max_depth` | `3` | Maximum thread depth for nested comments |
| `comment_rate_limit_seconds` | `60` | Minimum seconds between comments from one user |
| `verification_token_expiry_days` | `7` | Days before a comment verification token expires |
| `route_prefix` | `/blog` | URL prefix for all blog routes |

## Extensibility

### Preferences (Swap Implementations)

Use the `#[Preference]` attribute to swap any blog class with your own implementation. This lets you replace or override core behavior without forking:

```php
use Marko\Core\Attributes\Preference;

#[Preference(PostRepositoryInterface::class)]
class MyPostRepository implements PostRepositoryInterface
{
    // custom implementation
}
```

### Plugins (Hook Methods)

Use `#[Plugin]` with `#[Before]` and `#[After]` attributes to hook into any public method without overriding the entire class:

```php
use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\After;

#[Plugin(target: PostService::class)]
class PostServicePlugin
{
    #[Before]
    public function beforeCreatePost(array $data): null
    {
        // modify data before createPost runs
        return null;
    }

    #[After]
    public function afterCreatePost(Post $post): Post
    {
        // act on result after createPost runs
        return $post;
    }
}
```

### Observers (React to Events)

Use `#[Observer]` to react to blog lifecycle events without modifying core classes:

```php
use Marko\Core\Attributes\Observer;
use Marko\Blog\Events\Post\PostCreated;

#[Observer]
class PostCreatedObserver
{
    public function handle(PostCreated $event): void
    {
        // react to event — send notification, update cache, etc.
    }
}
```

Observers are ideal for event-driven side effects such as sending emails, clearing caches, or syncing to external services. Use an observer for every event reaction you need to add.

## Available Events

The blog dispatches the following lifecycle events:

| Event | Trigger |
|-------|---------|
| `PostCreated` | A new post is created |
| `PostUpdated` | A post is updated |
| `PostPublished` | A post is published |
| `PostDeleted` | A post is deleted |
| `CommentCreated` | A new comment is submitted |
| `CommentVerified` | A comment email is verified |
| `CommentDeleted` | A comment is deleted |
| `CategoryCreated` | A new category is created |
| `TagCreated` | A new tag is created |
| `AuthorCreated` | A new author is created |

## Routes

The blog registers the following public routes (prefix configurable via `route_prefix`):

| Method | Path | Description |
|--------|------|-------------|
| `GET /blog` | Post list | Paginated list of published posts |
| `GET /blog/{slug}` | Post detail | Single post view |
| `GET /blog/category/{slug}` | Category archive | Posts filtered by category |
| `GET /blog/tag/{slug}` | Tag archive | Posts filtered by tag |
| `GET /blog/author/{slug}` | Author archive | Posts filtered by author |
| `GET /blog/search` | Search results | Full-text post search |
| `POST /blog/{slug}/comment` | Submit comment | Submit a comment on a post |
| `GET /blog/comment/verify/{token}` | Verify comment | Verify a comment via email token |

## CLI Commands

| Command | Description |
|---------|-------------|
| `blog:publish-scheduled` | Publish any posts whose scheduled publish date has passed |
| `blog:cleanup` | Remove expired verification tokens and other stale data |

## Documentation

Full usage, configuration, events, API reference, and examples: [marko/blog](https://marko.build/docs/packages/blog/)
