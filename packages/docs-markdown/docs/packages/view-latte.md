---
title: marko/view-latte
description: Latte templating driver for the Marko Framework --- renders module-namespaced templates with automatic caching and strict types.
---

Latte templating driver for the Marko Framework. Implements `ViewInterface` from [`marko/view`](/docs/packages/view/) using the [Latte](https://latte.nette.org/) engine, with module-namespaced template resolution, automatic caching, and strict types.

## Installation

```bash
composer require marko/view-latte
```

This automatically installs `marko/view`.

## Configuration

Configure via the `view` config key:

```php title="config/view.php"
return [
    'cache_directory' => '/path/to/cache',
    'extension' => '.latte',
    'auto_refresh' => true,  // Set false in production
    'strict_types' => true,
];
```

## Usage

### Rendering Templates

Templates are rendered using the module namespace syntax:

```php
use Marko\View\ViewInterface;

$view->render('blog::post/index', ['posts' => $posts]);
```

The format is `module::path/to/template` where:
- `module` is the module name (e.g., `blog`, `admin`)
- `path/to/template` is the path within `resources/views/`

The `render()` method returns an HTTP `Response` object. Use `renderToString()` when you need the raw HTML --- for example, when rendering email bodies:

```php
use Marko\View\ViewInterface;

$html = $view->renderToString('blog::email/comment-verification/html', $data);
$text = $view->renderToString('blog::email/comment-verification/text', $data);
```

### Template Includes

All template includes use the same namespaced syntax:

```latte
{include 'blog::post/list/item', post: $post}
{include 'blog::pagination/index', pagination: $posts}
```

Relative paths (`../`) are not supported. This ensures:
- Consistent syntax throughout templates
- Templates can include from any module
- No fragile relative path dependencies

### Passing Data to Includes

Pass variables to included templates as named parameters:

```latte
{include 'blog::post/list/item', post: $post, showAuthor: true}
```

Default values in the included template:

```latte title="resources/views/post/list/item.latte"
{* post/list/item.latte *}
{default $showAuthor = true}
{default $linkAuthor = true}

<li class="post-item">
    <h2><a href="/blog/{$post->slug}">{$post->title}</a></h2>
    {if $showAuthor}
        <span>By {$post->getAuthor()->name}</span>
    {/if}
</li>
```

### Template Organization

All templates must live within at least one directory. No top-level template files.

#### Standard Structure

```
views/
  post/
    index.latte         # Post listing
    show.latte          # Single post
    list/
      item.latte        # Reusable list item
  category/
    show.latte
  tag/
    index.latte
  author/
    show.latte
  search/
    index.latte
    bar.latte           # Search input
  comment/
    form.latte
    thread.latte
  pagination/
    index.latte
```

#### Email Templates

Email templates group HTML and plain text versions together:

```
views/
  email/
    comment-verification/
      html.latte        # HTML version
      text.latte        # Plain text version
    welcome/
      html.latte
      text.latte
```

## API Reference

`LatteView` implements `ViewInterface` from [`marko/view`](/docs/packages/view/).

### Key Methods

| Method | Description |
|---|---|
| `render(string $template, array $data = []): Response` | Render a template and return an HTTP response |
| `renderToString(string $template, array $data = []): string` | Render a template and return the raw HTML string |

### Configuration Options

| Option | Type | Description |
|---|---|---|
| `cache_directory` | `string` | Directory for compiled template cache |
| `extension` | `string` | Template file extension (default `.latte`) |
| `auto_refresh` | `bool` | Recompile templates when source changes --- set `false` in production |
| `strict_types` | `bool` | Enable strict type checking in templates |
