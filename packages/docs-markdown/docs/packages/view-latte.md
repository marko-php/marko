---
title: marko/view-latte
description: Latte templating driver for the Marko Framework --- renders module-namespaced templates with automatic caching and strict types.
---

Latte templating driver for the Marko Framework. Implements `ViewInterface` from [`marko/view`](/docs/packages/view/) using the [Latte](https://latte.nette.org/) engine, with module-namespaced template resolution, automatic caching, and strict types.

## Installation

```bash
composer require marko/view-latte
```

This automatically installs `marko/view`. Note: `marko/view-latte` conflicts with `marko/view-twig` --- install only one view driver per project.

## Configuration

Configure via the `view` config key. Settings from `marko/view` (`cache_directory`, `auto_refresh`) apply to all drivers. The following are Latte-specific defaults:

```php title="config/view.php"
return [
    'cache_directory' => 'storage/views', // relative to the project root; created 0700
    'auto_refresh' => true,  // Set false in production
    'extension' => '.latte',
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

### Linking to Named Routes

The `route()` function builds URLs for [named routes](/docs/packages/routing/#named-routes-and-url-generation) through `UrlGeneratorInterface`, so templates don't hard-code paths:

```latte title="resources/views/post/list/item.latte"
<a href={route('blog.post.show', [slug: $post->slug])}>{$post->title}</a>
<a href={route('blog.index', [page: 2])}>Next page</a>             {* /blog?page=2 *}
<link rel="canonical" href={route('blog.post.show', [slug: $post->slug], absolute: true)}>
```

The arguments match `UrlGeneratorInterface::route(string $name, array $parameters = [], bool $absolute = false)`. Parameters the path doesn't use become the query string, and `absolute: true` uses `APP_URL`. Output is escaped like any other expression, so `&` in a query string renders as `&amp;` inside an attribute. An unknown name or a missing parameter throws `UrlGenerationException` and fails the render.

`route()` comes from `RouteFunctionProvider` in `marko/view`. Every [template function provider](/docs/packages/view/#template-functions) is registered with Latte by `TemplateFunctionExtension`: a function declared `isSafeHtml` has its result wrapped in `Latte\Runtime\Html` and prints unescaped, every other result is escaped. [Shared view data](/docs/packages/view/#shared-view-data) is merged into every `render()` and `renderToString()` call.

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

### Template Functions

| Function | Description |
|---|---|
| `route(string $name, array $parameters = [], bool $absolute = false): string` | URL for a named route (`Marko\View\Functions\RouteFunctionProvider` in `marko/view`) |

Functions from every other discovered `#[TemplateFunctions]` provider are available the same way.

### Configuration Options

| Option | Type | Description |
|---|---|---|
| `cache_directory` | `string` | Directory for compiled template cache (from `marko/view`). Defaults to `storage/views` under the project root; the factory refuses a directory that is world-writable or owned by another user --- see [compiled-template cache directory](/docs/packages/view/#compiled-template-cache-directory) |
| `auto_refresh` | `bool` | Recompile templates when source changes --- set `false` in production (from `marko/view`) |
| `extension` | `string` | Template file extension (default `.latte`) |
| `strict_types` | `bool` | Enable strict type checking in templates (default `true`) |
