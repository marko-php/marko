# Plan: View Package

## Created
2026-01-21

## Status
pending

## Objective
Implement the view/templating layer for Marko framework with an interface package (`marko/view`) and Latte driver implementation (`marko/view-latte`), following the established interface/driver split pattern.

## Scope

### In Scope
- `marko/view` package with interfaces, template resolver, view exceptions
- `marko/view-latte` package with Latte templating engine implementation
- `ViewInterface` - main rendering contract returning Response objects
- `TemplateResolverInterface` - module-based template path discovery
- `ViewConfig` - injectable configuration class
- Module-based template discovery (templates in `resources/views/` per module)
- Template fallback chain: app > modules > vendor (matches module override priority)
- Integration with routing Response objects
- Loud errors when no view driver installed
- Loud errors when template not found (with searched paths)
- Driver conflict handling if multiple view drivers installed
- Latte-specific features: auto-escaping, compilation caching, filters, extensions

### Out of Scope
- View components via attributes (future enhancement)
- Layout/section inheritance helpers (use Latte's native `{layout}` and `{block}`)
- Asset management/versioning (separate package)
- View caching beyond Latte's native compilation cache
- View composers/view sharing (controllers pass data explicitly)
- Named views/view aliases

## Success Criteria
- [ ] `ViewInterface` renders templates and returns Response objects
- [ ] `TemplateResolverInterface` resolves template names to file paths
- [ ] Template resolver searches module `resources/views/` directories
- [ ] Template fallback chain follows app > modules > vendor priority
- [ ] Controllers can inject `ViewInterface` and render templates
- [ ] Loud error when no view driver installed
- [ ] Loud error when template not found (lists searched paths)
- [ ] Loud error when multiple view drivers conflict
- [ ] Latte driver compiles templates to PHP
- [ ] Latte driver caches compiled templates in configurable directory
- [ ] Latte driver supports custom filters via configuration
- [ ] Blog package can render actual HTML posts
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | marko/view package scaffolding (composer.json, module.php) | - | pending |
| 002 | ViewException hierarchy | 001 | pending |
| 003 | ViewConfig value object | 001 | pending |
| 004 | TemplateResolverInterface | 001 | pending |
| 005 | ViewInterface contract | 003, 004 | pending |
| 006 | ModuleTemplateResolver (discovers templates in modules) | 004 | pending |
| 007 | marko/view-latte package scaffolding | 001 | pending |
| 008 | LatteEngineFactory | 007 | pending |
| 009 | LatteView implementation | 005, 006, 008 | pending |
| 010 | Latte module.php bindings | 007, 009 | pending |
| 011 | Unit tests for template resolution | 006 | pending |
| 012 | Unit tests for LatteView | 009 | pending |
| 013 | Integration tests | 010 | pending |
| 014 | Blog package view integration | 010 | pending |

## Architecture Notes

### Package Structure
```
packages/
  view/                   # Interface package
    src/
      Contracts/          # ViewInterface, TemplateResolverInterface
      Config/             # ViewConfig
      Exceptions/         # ViewException, TemplateNotFoundException, NoDriverException
      Resolution/         # ModuleTemplateResolver
    composer.json
    module.php
  view-latte/             # Latte implementation
    src/
      Factory/            # LatteEngineFactory
      LatteView.php       # ViewInterface implementation
    composer.json
    module.php
```

### ViewInterface Contract
```php
// packages/view/src/Contracts/ViewInterface.php
declare(strict_types=1);

namespace Marko\View\Contracts;

use Marko\Routing\Http\Response;

interface ViewInterface
{
    /**
     * Render a template and return an HTTP response.
     *
     * @param string $template Template name (e.g., 'blog::post/show')
     * @param array<string, mixed> $data Variables to pass to template
     * @return Response HTML response with rendered content
     */
    public function render(string $template, array $data = []): Response;

    /**
     * Render a template and return the HTML string.
     *
     * @param string $template Template name (e.g., 'blog::post/show')
     * @param array<string, mixed> $data Variables to pass to template
     * @return string Rendered HTML content
     */
    public function renderToString(string $template, array $data = []): string;
}
```

### Template Naming Convention
Templates use a `module::path` syntax:
- `blog::post/index` → looks for `resources/views/post/index.latte` in blog module
- `post/show` → looks in all modules (fallback chain)

### Template Resolution Flow
```
Template: "blog::post/show"
  ↓
1. Parse: module="blog", path="post/show"
  ↓
2. Search (in order):
   - app/blog/resources/views/post/show.latte
   - modules/*/blog/resources/views/post/show.latte
   - vendor/*/blog/resources/views/post/show.latte
  ↓
3. Return first match or throw TemplateNotFoundException
```

### TemplateResolverInterface
```php
// packages/view/src/Contracts/TemplateResolverInterface.php
declare(strict_types=1);

namespace Marko\View\Contracts;

interface TemplateResolverInterface
{
    /**
     * Resolve a template name to its absolute file path.
     *
     * @param string $template Template name (e.g., 'blog::post/show')
     * @return string Absolute path to template file
     * @throws TemplateNotFoundException When template cannot be found
     */
    public function resolve(string $template): string;

    /**
     * Get all paths that were searched for a template.
     * Useful for debugging TemplateNotFoundException.
     *
     * @param string $template Template name
     * @return array<string> List of searched paths
     */
    public function getSearchedPaths(string $template): array;
}
```

### ViewConfig
```php
// packages/view/src/Config/ViewConfig.php
declare(strict_types=1);

namespace Marko\View\Config;

class ViewConfig
{
    public function __construct(
        public readonly string $cacheDirectory,
        public readonly string $extension = '.latte',
        public readonly bool $autoRefresh = true,
        public readonly bool $strictTypes = true,
    ) {}
}
```

### Config Location
```php
// config/view.php
return [
    'cache_directory' => $_ENV['VIEW_CACHE_DIR'] ?? '/tmp/views',
    'extension' => '.latte',
    'auto_refresh' => $_ENV['APP_ENV'] !== 'production',
    'strict_types' => true,
];
```

### Controller Usage
```php
// packages/blog/src/Controllers/PostController.php
declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Repositories\PostRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\Contracts\ViewInterface;

class PostController
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/blog')]
    public function index(): Response
    {
        $posts = $this->repository->findAll();

        return $this->view->render('blog::post/index', [
            'posts' => $posts,
        ]);
    }

    #[Get('/blog/{slug}')]
    public function show(string $slug): Response
    {
        $post = $this->repository->findBySlug($slug);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        return $this->view->render('blog::post/show', [
            'post' => $post,
        ]);
    }
}
```

### Template Example
```latte
{* packages/blog/resources/views/post/show.latte *}
{layout 'blog::layouts/main'}

{block content}
<article>
    <h1>{$post->title}</h1>
    <time>{$post->createdAt|date:'F j, Y'}</time>
    <div class="content">
        {$post->content|noescape}
    </div>
</article>
{/block}
```

### Driver Conflict Handling
Only one view driver package can be installed. If multiple drivers are installed, the framework throws a loud error during boot:

```
BindingConflictException: Multiple implementations bound for ViewInterface.

Context: Both LatteView and LiquidView are attempting to bind.

Suggestion: Install only one view driver package. Remove one with:
  composer remove marko/view-latte
  or
  composer remove marko/view-liquid
```

### No Driver Installed Handling
If `marko/view` is installed without a driver, attempting to use view features throws:

```
ViewException: No view driver installed.

Context: Attempted to resolve ViewInterface but no implementation is bound.

Suggestion: Install a view driver package:
  composer require marko/view-latte
```

### Template Not Found Error
```
TemplateNotFoundException: Template 'blog::post/show' not found.

Context: Looking for 'post/show.latte' in module 'blog'

Searched paths:
  - /app/blog/resources/views/post/show.latte
  - /vendor/marko/blog/resources/views/post/show.latte

Suggestion: Create the template file or check the template name spelling.
```

### Latte module.php Bindings
```php
// packages/view-latte/module.php
declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\View\Contracts\ViewInterface;
use Marko\View\Contracts\TemplateResolverInterface;
use Marko\View\Latte\LatteView;
use Marko\View\Latte\Factory\LatteEngineFactory;

return [
    'enabled' => true,
    'bindings' => [
        ViewInterface::class => function (ContainerInterface $container): ViewInterface {
            return new LatteView(
                $container->get(LatteEngineFactory::class)->create(),
                $container->get(TemplateResolverInterface::class),
            );
        },
    ],
];
```

### Module Template Structure
```
packages/blog/
  src/
  resources/
    views/
      layouts/
        main.latte
      post/
        index.latte
        show.latte
  composer.json
  module.php
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Template resolution performance with many modules | Cache resolved paths; invalidate on module changes |
| Latte version compatibility | Pin to stable v3.x; test on PHP 8.5 |
| Complex template inheritance across modules | Document clear patterns; Latte's native `{layout}` handles this well |
| Module discovery for templates | Reuse existing ModuleRepository from core |
| Cache directory permissions | Clear error message if not writable |
