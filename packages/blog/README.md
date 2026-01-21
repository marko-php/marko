# Marko Blog

A database-driven blog module—works with MySQL, PostgreSQL, or any future driver.

## Overview

This module provides blog functionality using the repository pattern. It depends only on `marko/database` interfaces, making it database-agnostic. Install whichever driver your application needs.

## Installation

```bash
# Install blog with your preferred database driver
composer require marko/blog marko/database-mysql

# OR for PostgreSQL
composer require marko/blog marko/database-pgsql
```

## Current Features

- `GET /blog` — Blog index (lists all posts)
- `GET /blog/{slug}` — Single post by slug (404 if not found)

## Structure

```
blog/
  src/
    Controllers/
      PostController.php    # Routes with repository injection
    Entity/
      Post.php              # Entity with #[Table] and #[Column] attributes
    Repositories/
      PostRepository.php    # Data access extending Repository base
```

## Database Schema

The Post entity defines the `posts` table schema via attributes:

| Column | Type | Notes |
|--------|------|-------|
| id | INT | Primary key, auto-increment |
| title | VARCHAR | Required |
| slug | VARCHAR | Required, unique |
| content | TEXT | Required |
| created_at | TIMESTAMP | Nullable |
| updated_at | TIMESTAMP | Nullable |

Run migrations to create the table:

```bash
marko db:migrate
```

## Usage

### For Module Developers

The blog works automatically once a database driver is installed. Posts are fetched via the repository pattern:

```php
use Marko\Blog\Repositories\PostRepository;

public function __construct(
    private PostRepository $posts,
) {}

public function example(): void
{
    // Find all posts
    $allPosts = $this->posts->findAll();

    // Find by slug
    $post = $this->posts->findBySlug('my-post');

    // Find by ID
    $post = $this->posts->find(1);

    // Find by criteria
    $posts = $this->posts->findBy(['title' => 'Hello']);
}
```

### Post Entity

```php
use Marko\Blog\Entity\Post;

$post = new Post();
$post->title = 'My First Post';
$post->slug = 'my-first-post';
$post->content = 'Hello, world!';

$this->posts->save($post);
```

## Customizing in Your App

### Override the Controller

Use Preference to replace the controller:

```php
use Marko\Core\Attributes\Preference;
use Marko\Blog\Controllers\PostController;
use Marko\Blog\Repositories\PostRepository;

#[Preference(replaces: PostController::class)]
class MyPostController extends PostController
{
    public function __construct(
        PostRepository $repository,
        private MyCustomService $service,
    ) {
        parent::__construct($repository);
    }

    #[Get('/blog')]
    public function index(): Response
    {
        // Your custom implementation
    }
}
```

### Extend the Repository

```php
use Marko\Core\Attributes\Preference;
use Marko\Blog\Repositories\PostRepository;

#[Preference(replaces: PostRepository::class)]
class MyPostRepository extends PostRepository
{
    public function findPublished(): array
    {
        return $this->findBy(['status' => 'published']);
    }
}
```

## API Reference

### PostRepository

```php
public function find(int $id): ?Post;
public function findOrFail(int $id): Post;
public function findAll(): array;
public function findBy(array $criteria): array;
public function findOneBy(array $criteria): ?Post;
public function findBySlug(string $slug): ?Post;
public function save(Post $entity): void;
public function delete(Post $entity): void;
public function count(): int;
public function exists(int $id): bool;
```

## Roadmap

Features will be added as core supports them:

- Views/templates (when `marko/view` exists)
- Admin interface (when admin patterns are established)
