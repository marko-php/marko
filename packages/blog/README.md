# Marko Blog

A reference module demonstrating Marko patterns—built in lockstep with core features.

## Overview

This module serves as a working example of how to build with Marko. It only uses features that exist in core, showing real-world patterns for controllers, routing, and module structure. As core gains features, blog gains functionality.

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
    public function show(string $slug): Response
    {
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

This module intentionally stays minimal—it demonstrates patterns, not a full CMS.
