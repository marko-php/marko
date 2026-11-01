---
title: marko/view
description: View and template rendering interface — defines how controllers render templates, not which engine is used.
---

View and template rendering interface --- defines how controllers render templates, not which engine is used. View provides `ViewInterface` for rendering templates into HTTP responses, `TemplateResolverInterface` for resolving template names to file paths, and engine-agnostic contracts for template functions, shared view data and template precompilation, so a helper is written once and works in every engine. Templates use a `module::path` naming convention so modules own their views. This is an interface package; install a driver like `marko/view-latte` for the actual rendering engine.

## Installation

```bash
composer require marko/view
```

Note: You also need a view driver. Install `marko/view-latte` for Latte template support or `marko/view-twig` for Twig template support.

## Usage

### Rendering Templates

Inject `ViewInterface` in your controller and call `render()`:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

readonly class PostController
{
    public function __construct(
        private ViewInterface $view,
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

- `blog::post/show` --- resolves to the `blog` module's `resources/views/post/show` template
- `admin-panel::dashboard/index` --- resolves to the `admin-panel` module's dashboard view

The file extension is configured by the installed driver (e.g., `.latte` for `marko/view-latte`, `.twig` for `marko/view-twig`).

Template names are validated strictly before any file is touched. The path is one or more `/`-separated segments made only of `A-Z`, `a-z`, `0-9`, `_`, `-` and `.`, with an optional `module::` prefix. Names containing `..` or `.` segments, a leading `/`, empty segments, backslashes, NUL bytes or any other character throw `InvalidTemplateException`. After a template file is found, its real path must also sit inside the module's `resources/views/` directory, so a symlink pointing outside it is rejected as well.

Never build a template name directly from request input. Map user input to a fixed set of template names instead:

```php
$page = match ($request->query('page')) {
    'about' => 'pages::about',
    'contact' => 'pages::contact',
    default => 'pages::not-found',
};
```

### Template File Location

Place templates in your module's `resources/views/` directory:

```
mymodule/
  resources/
    views/
      post/
        index.{ext}
        show.{ext}
      layout.{ext}
```

The file extension depends on the installed driver (`.latte` for `marko/view-latte`, `.twig` for `marko/view-twig`).

### Resolving Template Paths

`TemplateResolverInterface` converts template names to absolute file paths:

```php
use Marko\View\TemplateResolverInterface;

readonly class TemplateFinder
{
    public function __construct(
        private TemplateResolverInterface $templateResolver,
    ) {}

    public function locate(
        string $template,
    ): string {
        return $this->templateResolver->resolve($template);
        // Returns: /path/to/blog/resources/views/post/show.latte (or .twig, etc.)
    }
}
```

If a template is not found, `TemplateNotFoundException` is thrown with all searched paths listed.

`all()` lists every template name across modules (for example `blog::post/show`), once each, in the form `resolve()` accepts. A template supplied by a `templates_for` module is listed under the module it supplies templates for. Files whose path is not a valid template name are left out.

### Configuration

The `ViewConfig` class provides typed access to view configuration values:

```php
use Marko\View\ViewConfig;

class MyService
{
    public function __construct(
        private ViewConfig $viewConfig,
    ) {}

    public function setup(): void
    {
        $cacheDir = $this->viewConfig->cacheDirectory();
        $autoRefresh = $this->viewConfig->autoRefresh();
    }
}
```

### Compiled-Template Cache Directory

Template engines compile views to PHP files and `include` them, so the cache directory is code-execution sensitive. `view.cache_directory` defaults to `storage/views`:

```php title="config/view.php"
return [
    'cache_directory' => 'storage/views', // relative to the project root
    'auto_refresh' => true,
];
```

Drivers pass the configured value through `CacheDirectoryGuard::prepare()` before handing it to the engine. The guard:

- resolves a relative path against the project root (`ProjectPaths::$base`), never the working directory --- FPM, CGI and mod_php `chdir` into `public/`
- creates a missing directory with mode `0700`
- throws `InsecureCacheDirectoryException` when the directory is world-writable or not owned by the user PHP runs as, so another local user cannot plant compiled PHP (the classic pre-created `/tmp/views` attack)

The ownership check requires the `posix` extension; without it only the world-writable check runs. Absolute paths are still allowed, but they must pass the same checks, so a shared `/tmp/views` is refused.

### Template Functions

Template functions are written once and work in every engine. A provider implements `TemplateFunctionProviderInterface`, carries the `#[TemplateFunctions]` attribute, and returns a list of `TemplateFunction` objects. The installed driver registers each one with its engine:

```php
use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

#[TemplateFunctions]
readonly class MoneyFunctionProvider implements TemplateFunctionProviderInterface
{
    public function functions(): array
    {
        return [
            new TemplateFunction(
                name: 'money',
                callable: fn (int $cents): string => '$' . number_format($cents / 100, 2),
            ),
            new TemplateFunction(
                name: 'spinner',
                callable: fn (): string => '<span class="spinner" aria-hidden="true"></span>',
                isSafeHtml: true,
            ),
        ];
    }
}
```

```latte
{money($order->totalCents)} {spinner()}
```

```twig
{{ money(order.totalCents) }} {{ spinner() }}
```

- Output is escaped like any other value. Set `isSafeHtml: true` only when the callable returns HTML you built yourself; Latte then prints it through `Latte\Runtime\Html`, Twig through `is_safe => ['html']`.
- Names must start with a letter and contain only letters, digits and `_`; anything else throws `ViewProviderException`.
- Two providers registering the same name throw `ViewProviderException` naming both classes. To change a function, replace its provider with a [Preference](/docs/packages/core/) instead of registering the name again.
- Providers are resolved through the container, so they can inject any service. `route()` is served this way by `RouteFunctionProvider`.

### Shared View Data

A view data provider adds variables to every render. Implement `ViewDataProviderInterface` and add the `#[ViewData]` attribute:

```php
use Marko\View\Attributes\ViewData;
use Marko\View\ViewDataProviderInterface;

#[ViewData]
readonly class SiteViewData implements ViewDataProviderInterface
{
    public function data(): array
    {
        return [
            'siteName' => 'Acme Store',
        ];
    }
}
```

Every driver passes the render data through `ViewDataMerger` before rendering, so `{$siteName}` (Latte) or `{{ siteName }}` (Twig) works in any template. `data()` runs on every render, so it may return per-request values. Data passed to `render()` or `renderToString()` wins over provider data with the same key. Two providers supplying the same key throw `ViewProviderException` naming both classes.

Both provider types are found by scanning each module's `src/` directory for the attributes. `marko discovery:cache` stores the provider class names (the `view_providers` section), so a cached boot does not scan.

### Precompiling Templates

`view:cache` compiles every template ahead of time, so the first requests after a deploy don't pay the compile cost and a syntax error fails the deploy instead of a page:

```bash
marko view:cache
```

The command lists templates with `TemplateResolverInterface::all()` and compiles each through the driver's `TemplatePrecompilerInterface`. Every template is attempted; each failure is printed with its file and line, and the command exits with `1` when any template fails. When the installed driver binds no precompiler, the command fails with `NoDriverException`.

Both official drivers bind a precompiler: [`marko/view-latte`](/docs/packages/view-latte/#precompiling-templates) and [`marko/view-twig`](/docs/packages/view-twig/#precompiling-templates). Only the template that wins resolution is compiled, because that is the one rendering uses: an `app/` override is compiled and the module template it shadows is skipped. A template whose source changed since it was last compiled is compiled again, even with `auto_refresh` off.

#### Deploying to production

Run `view:cache` on every deploy, after [`discovery:cache`](/docs/packages/core/#deploying-to-production), and turn `auto_refresh` off so rendering stops checking every template's modification time:

```bash
marko discovery:cache
marko view:cache
```

```php title="config/view.php"
return [
    'auto_refresh' => false, // templates are compiled by view:cache on deploy
];
```

With `auto_refresh` off, an edited template is only picked up by the next `view:cache`, so keep it `true` in development.

Run `view:cache` in the release step, on the server at the path the app runs from. Both engines key compiled files by the template's absolute path, so templates compiled during an image build at a different path are not reused at runtime: they are compiled again on first use.

`view:clear` removes every compiled template from `view.cache_directory` and keeps the directory:

```bash
marko view:clear
```

A driver implements precompilation by compiling one template name and wrapping its engine's compile error in `TemplatePrecompileException`:

```php
use Marko\View\Exceptions\TemplatePrecompileException;
use Marko\View\TemplatePrecompilerInterface;

class MyEnginePrecompiler implements TemplatePrecompilerInterface
{
    public function precompile(
        string $template,
    ): void {
        // Compile $template into the engine cache. On a syntax error:
        // throw TemplatePrecompileException::forTemplate($template, $path, $line, $reason, $previous);
    }
}
```

## Customization

Replace the template resolver via [Preferences](/docs/packages/core/) to customize how templates are located:

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
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

public function render(string $template, array $data = []): Response;
public function renderToString(string $template, array $data = []): string;
```

### TemplateResolverInterface

```php
use Marko\View\TemplateResolverInterface;

public function resolve(string $template): string;
public function getSearchedPaths(string $template): array;
public function all(): array;
```

### TemplateFunctionProviderInterface

```php
use Marko\View\TemplateFunctionProviderInterface;

public function functions(): array; // list<TemplateFunction>
```

### TemplateFunction

```php
use Marko\View\TemplateFunction;

public function __construct(string $name, Closure $callable, bool $isSafeHtml = false);
```

### ViewDataProviderInterface

```php
use Marko\View\ViewDataProviderInterface;

public function data(): array; // array<string, mixed>
```

### ViewDataMerger

```php
use Marko\View\ViewDataMerger;

public function merge(array $data): array;
```

### TemplateFunctionRegistry

```php
use Marko\View\TemplateFunctionRegistry;

public function all(): array; // list<TemplateFunction>, used by driver adapters
```

### TemplatePrecompilerInterface

```php
use Marko\View\TemplatePrecompilerInterface;

public function precompile(string $template): void;
```

### Commands

| Command | Description |
|---------|-------------|
| `view:cache` | Compile every template into the view cache with the driver's precompiler |
| `view:clear` | Remove every compiled template from `view.cache_directory` |

### ViewConfig

```php
use Marko\View\ViewConfig;

public function cacheDirectory(): string;
public function autoRefresh(): bool;
```

### CacheDirectoryGuard

```php
use Marko\View\CacheDirectoryGuard;

public function prepare(string $directory): string;
```

Returns the resolved absolute path. Throws `InsecureCacheDirectoryException` when the directory cannot be created, is a file, is world-writable, or is owned by another user.

### Exceptions

| Exception | Description |
|-----------|-------------|
| `ViewException` | Base exception for all view errors --- extends `MarkoException` |
| `TemplateNotFoundException` | Thrown when a template cannot be found --- includes all searched paths |
| `InvalidTemplateException` | Thrown when a template name is unsafe (path traversal, absolute path, NUL byte, backslash, disallowed characters) or resolves outside its module's `resources/views/` directory |
| `InsecureCacheDirectoryException` | Thrown when the compiled-template cache directory cannot be created, is not a directory, is world-writable, or is owned by another user |
| `NoDriverException` | Thrown when no view driver is installed --- suggests `composer require marko/view-latte` or `composer require marko/view-twig`. Also thrown by `view:cache` when the driver binds no `TemplatePrecompilerInterface` |
| `ViewProviderException` | Thrown when two providers register the same template function name or view data key, a function name is invalid, or a class marked `#[TemplateFunctions]`/`#[ViewData]` does not implement its interface |
| `TemplatePrecompileException` | Thrown by a precompiler when a template does not compile --- carries the template name, `path` and `templateLine` |

## Related Packages

- [`marko/view-latte`](/docs/packages/view-latte/) --- Latte templating driver
- [`marko/view-twig`](/docs/packages/view-twig/) --- Twig templating driver
