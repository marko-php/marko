# Marko Blog

A full-featured blog module—built in lockstep with core features.

## Overview

This module provides blog functionality using only the framework features that currently exist. As core packages are added (database, views, admin), this module gains real functionality. It's not a demo—it's a production blog that grows with the framework.

## Installation

```bash
composer require marko/blog
```

## Current Features

Basic routing with a post controller:

- `GET /blog` — Blog index
- `GET /blog/{slug}` — Single post

## Structure

```
blog/
  composer.json
  src/
    Controllers/
      PostController.php
```

## Example: PostController

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    #[Get('/blog')]
    public function index(): Response
    {
        return new Response('Blog Index');
    }

    #[Get('/blog/{slug}')]
    public function show(
        string $slug,
    ): Response {
        return new Response("Post: $slug");
    }
}
```

## Customizing in Your App

Override the controller via Preference:

```php
use Marko\Core\Attributes\Preference;
use Marko\Blog\Controllers\PostController;

#[Preference(replaces: PostController::class)]
class MyPostController extends PostController
{
    #[Get('/blog')]
    public function index(): Response
    {
        // Your custom implementation
        return new Response('My Blog');
    }
}
```

## Roadmap

Features will be added as core supports them:

- Database models (when `marko/database` exists)
- Views/templates (when `marko/view` exists)
- Admin interface (when admin patterns are established)

Full blog functionality will be available once the framework packages it depends on are complete.
