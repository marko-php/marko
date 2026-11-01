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
    'cache_directory' => 'storage/views', // relative to the project root; created 0700
    'auto_refresh' => true,  // false in production, with view:cache on deploy
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

### Linking to Named Routes

The `route()` function builds URLs for [named routes](/docs/packages/routing/#named-routes-and-url-generation) through `UrlGeneratorInterface`, so templates don't hard-code paths:

```twig title="resources/views/post/list/item.twig"
<a href="{{ route('blog.post.show', {slug: post.slug}) }}">{{ post.title }}</a>
<a href="{{ route('blog.index', {page: 2}) }}">Next page</a>  {# /blog?page=2 #}
<link rel="canonical" href="{{ route('blog.post.show', {slug: post.slug}, true) }}">
```

The arguments match `UrlGeneratorInterface::route(string $name, array $parameters = [], bool $absolute = false)`. Parameters the path doesn't use become the query string, and `true` as the third argument uses `APP_URL`. The function isn't marked safe, so its output is escaped like any other value (`&` renders as `&amp;`). An unknown name or a missing parameter throws `UrlGenerationException`. Twig wraps it in a `Twig\Error\RuntimeError` and the render fails.

`signedRoute()` builds a [signed URL](/docs/packages/routing/#signed-urls) through `UrlSignerInterface`, for links that leave the app, such as an unsubscribe link in an email template. It is absolute by default. Pass a `DateTimeImmutable` view variable as the third argument for a link that expires:

```twig title="resources/views/mail/newsletter.twig"
<a href="{{ signedRoute('newsletter.unsubscribe', {id: subscriber.id}) }}">Unsubscribe</a>
<a href="{{ signedRoute('share.show', {token: token}, expiresAt) }}">Open (valid for 24 hours)</a>
```

`route()` comes from `RouteFunctionProvider` and `signedRoute()` from `SignedRouteFunctionProvider`, both in `marko/view`. Every [template function provider](/docs/packages/view/#template-functions) is registered with Twig by `TemplateFunctionExtension`: a function declared `isSafeHtml` is registered with `is_safe => ['html']` and prints unescaped, every other result is escaped. [Shared view data](/docs/packages/view/#shared-view-data) is merged into every `render()` and `renderToString()` call.

### Precompiling Templates

`marko view:cache` compiles every template into `view.cache_directory` ahead of time. This package binds `TwigTemplatePrecompiler` as the [`TemplatePrecompilerInterface`](/docs/packages/view/#precompiling-templates) the command uses:

```bash
marko view:cache
```

It builds an environment with `TwigEngineFactory`, the same factory as rendering, and loads the template through the module loader, so the compiled file lands under the key rendering looks up: the template's resolved absolute path. A `Twig\Error\SyntaxError` is rethrown as `TemplatePrecompileException` with the template name, file and line. A broken template fails the command with its file and line:

```text
Template 'blog::post/show' failed to compile: Unknown "notATag" tag.
  in /var/www/app/blog/resources/views/post/show.twig:2
```

Only the template that wins resolution is compiled, so an `app/` override is compiled and the module template it shadows is skipped. Auto-refresh is always on while precompiling, so a template changed since the last compile is compiled again even when `auto_refresh` is `false`.

In production, run `view:cache` on every deploy, on the server at the path the app runs from, and set `auto_refresh` to `false`. Compiled files are keyed by absolute path, so templates compiled during an image build at another path are not reused. See [deploying to production](/docs/packages/view/#deploying-to-production).

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

`TwigView` implements `ViewInterface` and `TwigTemplatePrecompiler` implements `TemplatePrecompilerInterface`, both from [`marko/view`](/docs/packages/view/).

### Key Methods

| Method | Description |
|---|---|
| `render(string $template, array $data = []): Response` | Render a template and return an HTTP response |
| `renderToString(string $template, array $data = []): string` | Render a template and return the raw HTML string |
| `TwigTemplatePrecompiler::precompile(string $template): void` | Compile one template into the cache; throws `TemplatePrecompileException` on a syntax error |

### Template Functions

| Function | Description |
|---|---|
| `route(string $name, array $parameters = [], bool $absolute = false): string` | URL for a named route (`Marko\View\Functions\RouteFunctionProvider` in `marko/view`) |
| `signedRoute(string $name, array $parameters = [], ?DateTimeImmutable $expiresAt = null, bool $absolute = true): string` | Signed URL for a named route (`Marko\View\Functions\SignedRouteFunctionProvider` in `marko/view`) |

Functions from every other discovered `#[TemplateFunctions]` provider are available the same way.

### Configuration Options

| Option | Type | Description |
|---|---|---|
| `cache_directory` | `string` | Directory for compiled template cache (from `marko/view`). Defaults to `storage/views` under the project root; the factory refuses a directory that is world-writable or owned by another user --- see [compiled-template cache directory](/docs/packages/view/#compiled-template-cache-directory) |
| `auto_refresh` | `bool` | Recompile templates when source changes --- set `false` in production and run `view:cache` on deploy (from `marko/view`) |
| `extension` | `string` | Template file extension (default `.twig`) |
| `strict_variables` | `bool` | Throw an error for undefined variables (default `true`) |
| `autoescape` | `string` | Auto-escaping strategy: `html`, `js`, `css`, `url`, `name`, or `false` (default `html`) |
| `debug` | `bool` | Enable Twig debug mode (default `false`) |
| `charset` | `string` | Template charset (default `UTF-8`) |
