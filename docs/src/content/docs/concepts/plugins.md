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

A plugin is a class with the `#[Plugin]` attribute targeting the class you want to intercept. Methods use `#[Before]` and `#[After]` attributes and follow a naming convention:

```php title="PostRepositoryPlugin.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Blog\Repository\PostRepository;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: PostRepository::class)]
class PostRepositoryPlugin
{
    /**
     * Runs before getPost() — return null to continue, or non-null to short-circuit.
     */
    #[Before]
    public function beforeGetPost(int $id): null
    {
        // Log or validate the input
        return null;
    }

    /**
     * Runs after getPost() — receives the result, then the original arguments.
     */
    #[After]
    public function afterGetPost(array $result, int $id): array
    {
        // Enrich the result
        $result['retrieved_at'] = time();

        return $result;
    }
}
```

Plugins are discovered automatically from module `src/` directories — no manual registration needed.

### Method Naming

- `beforeMethodName` — runs before `methodName`
- `afterMethodName` — runs after `methodName`

For `before` plugins, parameters match the original method's signature. Return `null` to continue to the original method, or return a non-null value to short-circuit and use that as the result.

For `after` plugins, the first parameter is the result from the original method, followed by the original arguments. Return the (possibly modified) result.

### Sort Order

Use the `sortOrder` parameter to control the order when multiple plugins target the same method:

```php
#[Before(sortOrder: 10)]
public function beforeGetPost(int $id): null { /* runs first */ }
```

## Plugin Execution Order

When multiple plugins target the same method:

1. All `before` plugins run (in sort order)
2. The original method runs (unless a before plugin short-circuited)
3. All `after` plugins run (in sort order)

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
