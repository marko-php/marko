# Marko View

View and template rendering interface--defines how controllers render templates, not which engine is used.

## Overview

View provides `ViewInterface` for rendering templates into HTTP responses and `TemplateResolverInterface` for resolving template names to file paths. Templates use a `module::path` naming convention so modules own their views. This is an interface package; install a driver like `marko/view-latte` for the actual rendering engine.

## Installation

```bash
composer require marko/view
```

Note: You also need a view driver. Install `marko/view-latte` for Latte template support.

## Usage

### Rendering Templates

Inject `ViewInterface` in your controller and call `render()`:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class PostController
{
    public function __construct(
        private readonly ViewInterface $view,
    ) {}

    #[Get('/posts/{id}')]
    public function show(
        int $id,
    ): Response {
        return $this->view->render('blog::post/show', [
            'post' => $this->findPost($id),
        ]);
    }
}
```

`render()` returns a `Response` with the rendered HTML. Use `renderToString()` when you need the raw HTML:

```php
$html = $this->view->renderToString('blog::post/show', [
    'post' => $post,
]);
```

### Template Naming Convention

Templates follow the `module::path` pattern:

- `blog::post/show` -- resolves to the `blog` module's `resources/views/post/show` template
- `admin-panel::dashboard/index` -- resolves to the `admin-panel` module's dashboard view

The file extension is configured via `view.extension` in config (e.g., `.latte`).

### Template File Location

Place templates in your module's `resources/views/` directory:

```
mymodule/
  resources/
    views/
      post/
        index.latte
        show.latte
      layout.latte
```

### Resolving Template Paths

`TemplateResolverInterface` converts template names to absolute file paths:

```php
use Marko\View\TemplateResolverInterface;

class TemplateFinder
{
    public function __construct(
        private readonly TemplateResolverInterface $resolver,
    ) {}

    public function locate(
        string $template,
    ): string {
        return $this->resolver->resolve($template);
        // Returns: /path/to/blog/resources/views/post/show.latte
    }
}
```

If a template is not found, `TemplateNotFoundException` is thrown with all searched paths listed.

## Customization

Replace the template resolver via Preferences to customize how templates are located:

```php
use Marko\Core\Attributes\Preference;
use Marko\View\ModuleTemplateResolver;

#[Preference(replaces: ModuleTemplateResolver::class)]
class ThemeTemplateResolver extends ModuleTemplateResolver
{
    public function resolve(
        string $template,
    ): string {
        // Check theme directory first, then fall back
        return parent::resolve($template);
    }
}
```

## API Reference

### ViewInterface

```php
interface ViewInterface
{
    public function render(string $template, array $data = []): Response;
    public function renderToString(string $template, array $data = []): string;
}
```

### TemplateResolverInterface

```php
interface TemplateResolverInterface
{
    public function resolve(string $template): string;
    public function getSearchedPaths(string $template): array;
}
```
