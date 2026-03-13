---
title: Plugins
description: Intercept and modify method behavior without replacing entire classes.
---

Plugins let you modify the input or output of any public method on any class — without replacing the class itself. They're the fine-grained counterpart to [Preferences](/docs/concepts/preferences/).

## Plugin Types

Marko supports two plugin types:

| Type | When It Runs | What It Can Do |
|---|---|---|
| **Before** | Before the original method | Modify input arguments |
| **After** | After the original method | Modify the return value |

:::note
Marko intentionally does **not** support Around plugins. Around plugins (which wrap the entire method) are a common source of hard-to-debug issues in other frameworks. Before + After covers all legitimate use cases with better clarity.
:::

## Creating a Plugin

A plugin is a plain PHP class with methods that follow a naming convention:

```php title="PostRepositoryPlugin.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Blog\Repository\PostRepository;

class PostRepositoryPlugin
{
    /**
     * Modify arguments before getPost() is called.
     */
    public function beforeGetPost(PostRepository $subject, int $id): array
    {
        // Log or transform the input
        return [$id]; // Return modified arguments as array
    }

    /**
     * Modify the return value after getPost() completes.
     */
    public function afterGetPost(PostRepository $subject, array $result): array
    {
        // Add extra data to the result
        $result['retrieved_at'] = time();

        return $result;
    }
}
```

### Method Naming

- `beforeMethodName` — runs before `methodName`
- `afterMethodName` — runs after `methodName`

The first parameter is always `$subject` (the original object). For `before` plugins, remaining parameters match the original method's signature. Return an array of the (possibly modified) arguments.

For `after` plugins, the second parameter is the result from the original method. Return the (possibly modified) result.

## Registering Plugins

Plugins are registered in `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use App\MyApp\Plugin\PostRepositoryPlugin;
use Marko\Blog\Repository\PostRepository;

return [
    'plugins' => [
        PostRepository::class => [
            PostRepositoryPlugin::class,
        ],
    ],
];
```

## Plugin Execution Order

When multiple plugins target the same method:

1. All `before` plugins run (in module priority order)
2. The original method runs
3. All `after` plugins run (in module priority order)

## When to Use Plugins

Plugins are ideal for **cross-cutting concerns**:

- Adding logging to specific methods
- Transforming data before it's saved
- Adding validation before an action
- Enriching return values with additional data

If you need to replace the entire behavior, use a [Preference](/docs/concepts/preferences/) instead.

## Next Steps

- [Events & Observers](/docs/concepts/events/) — for decoupled, reactive behavior
- [Preferences](/docs/concepts/preferences/) — for full implementation replacement
- [Core Package](/docs/packages/core/) — API reference for the plugin system
