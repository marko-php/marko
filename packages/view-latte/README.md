# marko/view-latte

Latte templating driver for the Marko Framework.

## Installation

```bash
composer require marko/view-latte
```

## Usage

Templates are rendered using the module namespace syntax:

```php
$view->render('blog::post/index', ['posts' => $posts]);
```

The format is `module::path/to/template` where:
- `module` is the module name (e.g., `blog`, `admin`)
- `path/to/template` is the path within `resources/views/`

## Template Includes

All template includes use the same namespaced syntax:

```latte
{include 'blog::post/list/item', post: $post}
{include 'blog::pagination/index', pagination: $posts}
```

Relative paths (`../`) are not supported. This ensures:
- Consistent syntax throughout templates
- Templates can include from any module
- No fragile relative path dependencies

## Template Organization

All templates must live within at least one directory. No top-level template files.

### Standard Structure

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

### Email Templates

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

Usage:
```php
$html = $view->renderToString('blog::email/comment-verification/html', $data);
$text = $view->renderToString('blog::email/comment-verification/text', $data);
```

## Passing Data to Includes

Pass variables to included templates as named parameters:

```latte
{include 'blog::post/list/item', post: $post, showAuthor: true}
```

Default values in the included template:

```latte
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

## Configuration

Configure via the `view` config key:

```php
return [
    'view' => [
        'cache_directory' => '/path/to/cache',
        'extension' => '.latte',
        'auto_refresh' => true,  // Set false in production
        'strict_types' => true,
    ],
];
```
