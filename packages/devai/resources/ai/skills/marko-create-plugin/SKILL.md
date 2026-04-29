---
name: marko-create-plugin
description: Author a Marko plugin that intercepts a target class method using the #[Plugin], #[Before], and #[After] PHP attributes. Use whenever the user asks to create or write a plugin, intercept a method, modify input arguments before a method runs, modify a return value after a method runs, or short-circuit a method call. Marko intentionally does not support Around plugins — Before + After cover all legitimate cases.
---

# Create a Marko plugin

A plugin is a class that intercepts the input or output of a public method on any other class — without replacing that class. Plugins are Marko's fine-grained extensibility primitive, complementary to Preferences (which swap entire implementations). They are auto-discovered from any module's `src/` directory by the codeindexer; **no manual registration is needed**.

## When to use

The user wants to modify the arguments to, or the result from, a public method on a class they don't own (or don't want to subclass), without rewriting the class. Examples: enrich a return value, validate inputs, short-circuit on a cache hit, log calls, transform arguments.

## Plugin model — two timings, no Around

Marko supports exactly two timings:

| Attribute | When | What it does |
|---|---|---|
| `#[Before]` | Before the target method | Modify args, short-circuit, or pass through |
| `#[After]`  | After the target method  | Receive and modify the return value |

Around plugins are **intentionally absent**. Anything an Around could do can be expressed as a Before that short-circuits, an After that transforms the result, or both. This is a deliberate constraint to keep plugin chains debuggable.

## Step 1 — Identify the target

The plugin targets a class. The class can be from any module — your own, another package, or core. Plugin methods match the target's method names exactly.

```php
#[Plugin(target: PostRepository::class)]
```

If the target is in a package the user controls, ensure the targeted method is `public`. Plugins cannot intercept `protected` or `private` methods.

## Step 2 — Author the plugin class

```php
<?php

declare(strict_types=1);

namespace App\Blog\Plugin;

use App\Blog\Repository\PostRepository;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: PostRepository::class)]
class PostRepositoryPlugin
{
    #[Before]
    public function getPost(int $id): null
    {
        // Pass-through: log, validate, observe — without changing inputs
        return null;
    }

    #[After]
    public function find(mixed $result, int $id): mixed
    {
        if (is_array($result)) {
            $result['retrieved_at'] = time();
        }

        return $result;
    }
}
```

## Step 3 — Understand the three Before return modes

A `#[Before]` method can return three different shapes, each with distinct semantics:

| Return value | Effect |
|---|---|
| `null` | Pass-through. The original method runs with the original arguments. |
| `array` | Replace the arguments. The original method runs with these values in order. |
| Any non-null, non-array value | Short-circuit. The original method is **not** called; this value becomes the result. |

**Pass-through** (observe without mutating):

```php
#[Before]
public function save(Post $post): null
{
    $this->logger->info('Saving post', ['id' => $post->id]);

    return null;
}
```

**Argument modification** (replace inputs):

```php
#[Before]
public function applyDiscount(float $price, int $quantity): null|array
{
    if ($quantity >= 10) {
        return [$price * 0.9, $quantity];
    }

    return null;
}
```

**Short-circuit** (skip the original method):

```php
#[Before]
public function show(string $slug): ?string
{
    if ($slug === 'old-post') {
        return 'new-post';
    }

    return null;
}
```

## Step 4 — Understand After signatures

`#[After]` methods receive the result first, then the (possibly modified) original arguments. Return the (possibly transformed) result.

```php
#[After]
public function find(mixed $result, int $id): mixed
{
    // $result = whatever the original returned (or whatever a previous After returned)
    // $id     = the (possibly modified) argument
    return $this->enrich($result);
}
```

After plugins chain in declared order; each one's return value feeds the next.

## Step 5 — Control plugin order with sortOrder

When multiple plugins target the same method, control execution order with `sortOrder` on the `#[Before]` or `#[After]` attribute. Default is `0`. Lower values run first; negatives are valid.

```php
#[Before(sortOrder: -10)]  // Runs before plugins with default sortOrder
public function save(Post $post): null { /* … */ }

#[After(sortOrder: 100)]   // Runs after lower-priority Afters
public function find(mixed $result, int $id): mixed { /* … */ }
```

## Step 6 — Method-name override (rare)

By default the plugin method name matches the target method name. To override, pass `method:`:

```php
#[Before(method: 'save')]
public function logSaveAttempt(Post $post): null { /* … */ }
```

Use this only when the plugin method name would collide with another (e.g. one plugin class targeting two methods that share a name with reserved class methods).

## Step 7 — Place and verify

- Place the plugin class anywhere under your module's `src/` (a `Plugin/` subdirectory is conventional but not required).
- The plugin is discovered automatically — **do not** register it in `module.php`.
- Verify it loaded by asking the agent to call the MCP tool `find_plugins_targeting` with the target class. Your plugin should appear in the result.

## Constraints

- Targeted methods must be `public` on the target class
- The target class must not be `final` (Marko avoids `final` for this reason)
- Plugin classes themselves should be `readonly` when they have no mutable state
- Constructor property promotion always
- `declare(strict_types=1)` always

## What this skill does not cover

- Creating an entire new module to host the plugin — see the `marko-create-module` skill
- Replacing an entire class — that's a Preference, not a Plugin (different concept)
- Listening to events vs. intercepting methods — events are observed via `#[Observer]`, plugins intercept methods

## See also

- [Marko docs: plugins](https://marko.build/docs/concepts/plugins/)
- [Marko docs: preferences](https://marko.build/docs/concepts/preferences/) — for replacing classes wholesale
- [`marko/core` README](https://github.com/markshust/marko/tree/develop/packages/core)
