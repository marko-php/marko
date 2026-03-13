---
title: Build a Blog
description: Step-by-step tutorial — build a blog with Marko from scratch.
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

This creates the `posts`, `comments`, and related tables from the blog package's entities.

## Step 3: Seed Sample Data

```bash
marko db:seed
```

The blog package includes seeders that create sample posts.

## Step 4: Start the Server

```bash
marko up
```

Visit `http://localhost:8000/blog` — you should see the blog index with sample posts.

## Step 5: Explore the Routes

The `marko/blog` package registers these routes automatically:

| Route | Description |
|---|---|
| `GET /blog` | Post listing |
| `GET /blog/{slug}` | Single post |
| `POST /blog/{slug}/comments` | Add comment |
| `GET /blog/category/{slug}` | Posts by category |

## Step 6: Customize Templates

Blog templates use Latte and can be overridden by placing files in your app module:

```
app/blog/resources/views/
├── post/
│   ├── index.latte    # Post listing
│   └── show.latte     # Single post
└── comment/
    └── form.latte     # Comment form
```

For example, override the post listing:

```html title="app/blog/resources/views/post/index.latte"
<h1>My Blog</h1>

{foreach $posts as $post}
    <article>
        <h2><a href="/blog/{$post->slug}">{$post->title}</a></h2>
        <p>{$post->excerpt}</p>
        <time>{$post->createdAt|date:'M j, Y'}</time>
    </article>
{/foreach}
```

## Step 7: Add Authentication

Protect the comment form so only logged-in users can comment:

```bash
composer require marko/authentication
```

The blog package already dispatches events you can observe:

```php title="app/blog/module.php"
use Marko\Blog\Event\CommentCreatedEvent;
use App\Blog\Observer\NotifyAuthorOfComment;

return [
    'observers' => [
        CommentCreatedEvent::class => [
            NotifyAuthorOfComment::class,
        ],
    ],
];
```

## Step 8: Extend with Plugins

Want to add reading time to every post? Use a plugin:

```php title="app/blog/src/Plugin/AddReadingTimePlugin.php"
<?php

declare(strict_types=1);

namespace App\Blog\Plugin;

use Marko\Blog\Repository\PostRepository;

class AddReadingTimePlugin
{
    public function afterFindBySlug(PostRepository $subject, ?array $result): ?array
    {
        if ($result === null) {
            return null;
        }

        $wordCount = str_word_count($result['body']);
        $result['reading_time_minutes'] = max(1, (int) ceil($wordCount / 200));

        return $result;
    }
}
```

Register it:

```php title="app/blog/module.php"
use Marko\Blog\Repository\PostRepository;
use App\Blog\Plugin\AddReadingTimePlugin;

return [
    'plugins' => [
        PostRepository::class => [
            AddReadingTimePlugin::class,
        ],
    ],
];
```

## What You've Learned

- How to scaffold a Marko project and install packages
- Database setup with migrations and seeders
- Template overriding for customization
- Events and observers for reactive behavior
- Plugins for modifying existing functionality

## Next Steps

- [Build a REST API](/docs/tutorials/build-a-rest-api/) — create a JSON API
- [Authentication deep dive](/docs/guides/authentication/) — custom guards and providers
- [Deploy your application](#) — production deployment guide (coming soon)
