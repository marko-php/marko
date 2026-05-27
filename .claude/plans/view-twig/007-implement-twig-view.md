# Task 007: Implement TwigView

**Status**: pending
**Depends on**: 005, 006
**Retry count**: 0

## Description
Create `TwigView` implementing `ViewInterface`. Wraps `Twig\Environment`, sets the `ModuleLoader` on construction so includes use module resolution, and exposes both `render()` (returns HTTP `Response`) and `renderToString()` (returns string). Mirrors the contract and behavior of `LatteView`.

## Context
- Related files:
  - `packages/view-twig/src/TwigView.php` (new)
  - `packages/view-twig/tests/TwigViewTest.php` (new)
  - `packages/view-latte/src/LatteView.php` (reference pattern)
  - `packages/view/src/ViewInterface.php` (contract)
  - `packages/routing/src/Http/Response.php` (for `Response::html()`)
- Constructor signature: `__construct(Twig\Environment $engine, TemplateResolverInterface $resolver)`
- Constructor body: call `$this->engine->setLoader(new ModuleLoader($resolver))` so includes resolve correctly (mirrors view-latte pattern). `Twig\Environment::setLoader(LoaderInterface)` is a public method in Twig 3.
- `render(string $template, array $data = []): Response` — render to string, return `Response::html($html)`
- `renderToString(string $template, array $data = []): string` — call `$engine->render($template, $data)` and return result

## Test Setup Notes
- Tests that need a real `Twig\Environment` should construct one with `new \Twig\Environment(new \Twig\Loader\ArrayLoader([]), [...])` — `TwigView`'s constructor will immediately replace the loader. Make sure to pass `strict_variables => true` and `autoescape => 'html'` in the options so behavioral tests reflect production defaults.
- The "auto-escapes HTML by default" test should render a template containing `{{ value }}` with a value of `'<script>'` and assert the output contains `&lt;script&gt;`.
- The "throws on undefined variable" test should render a template referencing `{{ undefined_var }}` and assert a `\Twig\Error\RuntimeError` is thrown (Twig 3's strict_variables error type).
- The "resolves module-namespaced includes" test should mirror view-latte's pattern: define a parent template that does `{% include 'blog::post/list/item' %}` and verify the include is resolved via the injected `TemplateResolverInterface`.

## Requirements (Test Descriptions)
- [ ] `it renders a template and returns an HTML Response`
- [ ] `it renders a template to a string`
- [ ] `it passes data variables to the template`
- [ ] `it resolves module-namespaced template includes via the resolver`
- [ ] `it auto-escapes HTML output by default`
- [ ] `it throws a Twig error when an undefined variable is used and strict_variables is true`

## Acceptance Criteria
- `TwigView` implements `Marko\View\ViewInterface`
- Constructor takes `Twig\Environment` and `TemplateResolverInterface`
- Constructor sets `ModuleLoader` on the engine so includes use module resolution
- `render()` returns a `Response::html()` instance
- `renderToString()` returns the rendered string directly
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
