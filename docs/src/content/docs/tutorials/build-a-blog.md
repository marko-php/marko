---
title: Build a Blog
description: Step-by-step tutorial --- build a blog with Marko from scratch.
---

This tutorial walks you through building a fully functional blog with posts, comments, and authentication using Marko.

## What You'll Build

- A blog with posts and comments
- User authentication (login/register)
- An admin area for managing posts
- Database-backed persistence

## Prerequisites

- PHP 8.5+
- Composer 2.x
- PostgreSQL (or MySQL)

## Step 1: Create the Project

```bash
composer create-project marko/skeleton my-blog
cd my-blog
composer require marko/blog
```

The `marko/blog` package provides post and comment functionality out of the box.

## Step 2: Configure the Database

Edit your `.env`:

```bash title=".env"
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=my_blog
DB_USERNAME=marko
DB_PASSWORD=secret
```

Run the migrations:

```bash
marko db:migrate
```

The blog package defines its schema using entity attributes --- `#[Table]`, `#[Column]`, and `#[Index]` --- on entity classes like `Post` and `Comment`. When you run `marko db:migrate`, it reads these attributes and auto-generates the migrations. Here is a simplified view of the `Post` entity:

```php title="packages/blog/src/Entity/Post.php"
<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('posts')]
#[Index('idx_posts_author_id', ['author_id'])]
#[Index('idx_posts_status', ['status'])]
#[Index('idx_posts_published_at', ['published_at'])]
class Post extends Entity implements PostInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $slug;

    #[Column]
    public string $title = '';

    #[Column(type: 'TEXT')]
    public string $content = '';

    #[Column('author_id', references: 'authors.id')]
    public int $authorId = 0;

    #[Column(type: 'TEXT')]
    public ?string $summary = null;

    #[Column('published_at')]
    public ?string $publishedAt = null;

    #[Column('created_at')]
    public ?string $createdAt = null;

    #[Column('updated_at')]
    public ?string $updatedAt = null;

    // ...
}
```

You never write SQL or migration files by hand --- the entity attributes are the single source of truth.

## Step 3: Start the Server

```bash
marko up
```

Visit `http://localhost:8000/blog` --- you should see the blog index.

## Step 4: Explore the Routes

The `marko/blog` package registers these routes automatically:

| Route | Description |
|---|---|
| `GET /blog` | Post listing |
| `GET /blog/{slug}` | Single post |
| `POST /blog/{slug}/comment` | Add comment |
| `GET /blog/category/{slug}` | Posts by category |
| `GET /blog/tag/{slug}` | Posts by tag |
| `GET /blog/author/{slug}` | Posts by author |
| `GET /blog/search` | Search posts |

## Step 5: Customize Templates

Blog templates use [Latte](https://latte.nette.org/) and can be overridden by placing files in your app module:

```
app/blog/resources/views/
├── post/
│   ├── index.latte    # Post listing
│   └── show.latte     # Single post
└── comment/
    └── form.latte     # Comment form
```

For example, override the post listing:

```latte title="app/blog/resources/views/post/index.latte"
<main>
    <h1>My Blog</h1>
    <p n:if="$posts->isEmpty()" class="no-posts">There are no posts yet.</p>
    <ul n:if="!$posts->isEmpty()" class="post-list">
        {foreach $posts->items as $post}
            <li>
                <article>
                    <h2><a href="/blog/{$post->slug}">{$post->title}</a></h2>
                    <p n:if="$post->summary">{$post->summary}</p>
                    <time datetime="{$post->publishedAt}">
                        {$post->getPublishedAt()->format('F j, Y')}
                    </time>
                </article>
            </li>
        {/foreach}
    </ul>
</main>
```

Templates access entity properties directly --- `$post->title`, `$post->slug`, `$post->summary` --- and use getter methods like `$post->getPublishedAt()` for computed values.

## Step 6: Add Authentication

Protect the comment form so only logged-in users can comment:

```bash
composer require marko/authentication
```

The blog package dispatches events you can observe. Create an observer class with the `#[Observer]` attribute:

```php title="app/blog/src/Observer/NotifyAuthorOfComment.php"
<?php

declare(strict_types=1);

namespace App\Blog\Observer;

use Marko\Blog\Events\Comment\CommentCreated;
use Marko\Core\Attributes\Observer;

#[Observer(event: CommentCreated::class)]
class NotifyAuthorOfComment
{
    public function handle(CommentCreated $event): void
    {
        // Send notification to the post author...
    }
}
```

## Step 7: Extend with Plugins

Want to add reading time to every post? Use a plugin:

```php title="app/blog/src/Plugin/AddReadingTimePlugin.php"
<?php

declare(strict_types=1);

namespace App\Blog\Plugin;

use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: PostRepositoryInterface::class)]
class AddReadingTimePlugin
{
    #[After]
    public function findBySlug(?Post $result): ?Post
    {
        if ($result === null) {
            return null;
        }

        $wordCount = str_word_count($result->content);
        $result->readingTimeMinutes = max(1, (int) ceil($wordCount / 200));

        return $result;
    }
}
```

## What You've Learned

- How to scaffold a Marko project and install packages
- Entity-driven database schema with `#[Table]`, `#[Column]`, and `#[Index]` attributes
- Template overriding with Latte for customization
- [Events and observers](/docs/concepts/events/) for reactive behavior
- [Plugins](/docs/concepts/plugins/) for modifying existing functionality

## Next Steps

- [Build a REST API](/docs/tutorials/build-a-rest-api/) --- create a JSON API
- [Create a Custom Module](/docs/tutorials/custom-module/) --- build a reusable Composer package
