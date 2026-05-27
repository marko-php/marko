# marko/view-twig

Twig templating driver for the Marko Framework.

## Installation

```bash
composer require marko/view-twig
```

Note: `marko/view-twig` conflicts with `marko/view-latte` --- install only one view driver per project.

## Quick Example

```php
$view->render('blog::post/index', ['posts' => $posts]);
```

## Documentation

Full usage, API reference, and examples: [marko/view-twig](https://marko.build/docs/packages/view-twig/)
