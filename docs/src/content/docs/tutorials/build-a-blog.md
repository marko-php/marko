---
title: Build a Blog
description: Step-by-step tutorial --- build a blog with Marko from scratch.
---

This tutorial walks you through building a blog from scratch using Marko's core packages. You'll define entities, routes, templates, and extend behavior with plugins.

## What You'll Build

- A Post entity with database persistence
- A repository with query builder
- Controllers with attribute-based routing
- Latte templates for rendering
- A plugin to add reading time to posts

## Prerequisites

- PHP 8.5+
- Composer 2.x
- PostgreSQL (or MySQL)

## Step 1: Create the Project

```bash
composer create-project marko/skeleton my-blog
cd my-blog
```

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

## Step 3: Define the Post Entity

Create an entity with database attributes. Marko reads `#[Table]`, `#[Column]`, and `#[Index]` to auto-generate migrations --- no SQL by hand.

```php title="app/blog/src/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('posts')]
#[Index('idx_posts_slug', ['slug'])]
#[Index('idx_posts_published_at', ['published_at'])]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $slug;

    #[Column]
    public string $title = '';

    #[Column(type: 'TEXT')]
    public string $content = '';

    #[Column]
    public bool $published = false;

    #[Column('published_at')]
    public ?string $publishedAt = null;

    #[Column('created_at')]
    public ?string $createdAt = null;
}
```

Run the migration:

```bash
marko db:migrate
```

## Step 4: Create a Repository

Extend the base `Repository` class and use the query builder for custom queries:

```php title="app/blog/src/Repository/PostRepository.php"
<?php

declare(strict_types=1);

namespace App\Blog\Repository;

use App\Blog\Entity\Post;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const string ENTITY_CLASS = Post::class;

    public function findBySlug(string $slug): ?Post
    {
        /** @var Post|null */
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return array<Post>
     */
    public function findPublished(): array
    {
        return $this->query()
            ->whereNotNull('published_at')
            ->where('published', '=', true)
            ->orderBy('published_at', 'DESC')
            ->getEntities();
    }
}
```

The base `Repository` gives you `find()`, `findAll()`, `findBy()`, `findOneBy()`, `save()`, and `delete()` out of the box. The `query()` method returns a fluent query builder with automatic entity hydration via `getEntities()`.

## Step 5: Add Routes and Controllers

```php title="app/blog/src/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use App\Blog\Repository\PostRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class PostController
{
    public function __construct(
        private PostRepository $postRepository,
        private ViewInterface $view,
    ) {}

    #[Get('/blog')]
    public function index(): Response
    {
        return $this->view->render('blog::post/index', [
            'posts' => $this->postRepository->findPublished(),
        ]);
    }

    #[Get('/blog/{slug}')]
    public function show(string $slug): Response
    {
        $post = $this->postRepository->findBySlug($slug);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        return $this->view->render('blog::post/show', [
            'post' => $post,
        ]);
    }
}
```

## Step 6: Create Templates

```latte title="app/blog/resources/views/post/index.latte"
<main>
    <h1>Blog</h1>
    <ul n:if="$posts">
        {foreach $posts as $post}
            <li>
                <article>
                    <h2><a href="/blog/{$post->slug}">{$post->title}</a></h2>
                    <time datetime="{$post->publishedAt}">{$post->publishedAt}</time>
                </article>
            </li>
        {/foreach}
    </ul>
    <p n:if="!$posts">No posts yet.</p>
</main>
```

```latte title="app/blog/resources/views/post/show.latte"
<article>
    <h1>{$post->title}</h1>
    <time datetime="{$post->publishedAt}">{$post->publishedAt}</time>
    <div class="content">{$post->content|noescape}</div>
</article>
```

## Step 7: Start the Server

```bash
marko up
```

Visit `http://localhost:8000/blog` to see your blog.

## Step 8: Extend with a Plugin

Add reading time to every post without modifying the repository:

```php title="app/blog/src/Plugin/AddReadingTimePlugin.php"
<?php

declare(strict_types=1);

namespace App\Blog\Plugin;

use App\Blog\Entity\Post;
use App\Blog\Repository\PostRepository;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: PostRepository::class)]
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

Now `$post->readingTimeMinutes` is available in your templates.

## What You've Learned

- Entity-driven database schema with `#[Table]`, `#[Column]`, and `#[Index]` attributes
- Repositories with built-in CRUD and fluent query builder
- Attribute-based routing with `#[Get]` on controller methods
- Latte templates for rendering views
- [Plugins](/docs/concepts/plugins/) for modifying existing functionality without editing source

## Next Steps

- [Database guide](/docs/guides/database/) --- deep dive into entities, migrations, and querying
- [Build a REST API](/docs/tutorials/build-a-rest-api/) --- create a JSON API
- [Create a Custom Module](/docs/tutorials/custom-module/) --- build a reusable Composer package
