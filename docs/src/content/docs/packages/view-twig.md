---
title: marko/view-twig
description: Twig templating driver for the Marko Framework --- renders module-namespaced templates with automatic caching and configurable escaping.
---

Twig templating driver for the Marko Framework. Implements `ViewInterface` from [`marko/view`](/docs/packages/view/) using the [Twig](https://twig.symfony.com/) engine, with module-namespaced template resolution and automatic caching.

## Installation

```bash
composer require marko/view-twig
```

This automatically installs `marko/view`. Note: `marko/view-twig` conflicts with `marko/view-latte` --- install only one view driver per project.

## Configuration

Configure via the `view` config key. Settings from `marko/view` (`cache_directory`, `auto_refresh`) apply to all drivers. The following are Twig-specific defaults:

```php title="config/view.php"
return [
    'cache_directory' => '/path/to/cache',
    'auto_refresh' => true,  // Set false in production
    'extension' => '.twig',
    'strict_variables' => true,
    'autoescape' => 'html',
    'debug' => false,
    'charset' => 'UTF-8',
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

```twig
{% include 'blog::post/list/item' with { post: post } %}
{% include 'blog::pagination/index' with { pagination: posts } %}
```

Relative paths are not supported. This ensures:
- Consistent syntax throughout templates
- Templates can include from any module
- No fragile relative path dependencies

### Passing Data to Includes

Pass variables to included templates as named parameters:

```twig
{% include 'blog::post/list/item' with { post: post, showAuthor: true } %}
```

Default values in the included template:

```twig title="resources/views/post/list/item.twig"
{# post/list/item.twig #}
{% set showAuthor = showAuthor ?? true %}
{% set linkAuthor = linkAuthor ?? true %}

<li class="post-item">
    <h2><a href="/blog/{{ post.slug }}">{{ post.title }}</a></h2>
    {% if showAuthor %}
        <span>By {{ post.author.name }}</span>
    {% endif %}
</li>
```

### Template Organization

All templates must live within at least one directory. No top-level template files.

#### Standard Structure

```
views/
  post/
    index.twig         # Post listing
    show.twig          # Single post
    list/
      item.twig        # Reusable list item
  category/
    show.twig
  tag/
    index.twig
  author/
    show.twig
  search/
    index.twig
    bar.twig           # Search input
  comment/
    form.twig
    thread.twig
  pagination/
    index.twig
```

#### Email Templates

Email templates group HTML and plain text versions together:

```
views/
  email/
    comment-verification/
      html.twig        # HTML version
      text.twig        # Plain text version
    welcome/
      html.twig
      text.twig
```

## API Reference

`TwigView` implements `ViewInterface` from [`marko/view`](/docs/packages/view/).

### Key Methods

| Method | Description |
|---|---|
| `render(string $template, array $data = []): Response` | Render a template and return an HTTP response |
| `renderToString(string $template, array $data = []): string` | Render a template and return the raw HTML string |

### Configuration Options

| Option | Type | Description |
|---|---|---|
| `cache_directory` | `string` | Directory for compiled template cache (from `marko/view`) |
| `auto_refresh` | `bool` | Recompile templates when source changes --- set `false` in production (from `marko/view`) |
| `extension` | `string` | Template file extension (default `.twig`) |
| `strict_variables` | `bool` | Throw an error for undefined variables (default `true`) |
| `autoescape` | `string` | Auto-escaping strategy: `html`, `js`, `css`, `url`, `name`, or `false` (default `html`) |
| `debug` | `bool` | Enable Twig debug mode (default `false`) |
| `charset` | `string` | Template charset (default `UTF-8`) |
