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

A plugin is a class with the `#[Plugin]` attribute targeting the class you want to intercept. Methods use `#[Before]` and `#[After]` attributes. The method name is the target method name — the attribute determines whether it runs before or after.

```php title="PostRepositoryPlugin.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Blog\Repositories\PostRepository;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: PostRepository::class)]
class PostRepositoryPlugin
{
    /**
     * Runs before getPost() — return null to pass through, return an array to modify
     * the arguments, or return a non-null non-array value to short-circuit entirely.
     */
    #[Before]
    public function getPost(int $id): null
    {
        // Log or validate the input without changing anything
        return null;
    }

    /**
     * Runs after find() — receives the result, then the arguments (possibly modified by before plugins).
     */
    #[After]
    public function find(mixed $result, int $id): mixed
    {
        // Enrich the result
        $result['retrieved_at'] = time();

        return $result;
    }
}
```

Plugins are discovered automatically from module `src/` directories — no manual registration needed.

:::note[Migration note]
Previously, plugin methods used `before`/`after` prefixes (e.g., `beforeSave`). The method name now matches the target directly.
:::

### Method Naming

The method name is the target method name. The attribute determines timing:

- `#[Before]` on a method named `save` — runs before `save()`
- `#[After]` on a method named `save` — runs after `save()`

For `before` plugins, parameters match the original method's signature. There are three possible return behaviors:

- Return `null` — pass through and continue to the original method unchanged
- Return an `array` — replace the method's arguments with the array values before calling the original method
- Return any other non-null value — short-circuit: the original method is skipped and this value becomes the result

**Argument modification example** — return an array to replace the arguments:

```php
#[Before]
public function applyDiscount(float $price, int $quantity): null|array
{
    // Apply bulk discount when ordering 10 or more
    if ($quantity >= 10) {
        return [$price * 0.9, $quantity]; // 10% discount on price
    }

    return null; // No modification — continue with original arguments
}
```

**Short-circuit example** — return a non-null, non-array value to skip the original method:

```php
#[Before]
public function show(string $slug): ?string
{
    // Redirect old slugs to the canonical one
    if ($slug === 'old-post') {
        return 'new-post'; // Short-circuit — original method is never called
    }

    return null; // Continue to original method
}
```

For `after` plugins, the first parameter is the result from the original method, followed by the arguments (possibly modified by before plugins). Return the (possibly modified) result.

### Sort Order

Use the `sortOrder` parameter to control the order when multiple plugins target the same method:

```php
#[Before(sortOrder: 10)]
public function getPost(int $id): null { /* runs first */ }
```

## Advanced: Explicit Method Targeting

When you need both a `before` and an `after` interceptor for the same target method inside one plugin class, you cannot give two methods the same name. Use the `method:` parameter to specify the target method while giving each plugin method a distinct name.

```php title="OrderAuditPlugin.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Commerce\Service\OrderService;
use Marko\Commerce\ValueObject\PaymentRequest;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: OrderService::class)]
class OrderAuditPlugin
{
    /**
     * Runs before processPayment() — method name is completely different from target.
     */
    #[Before(method: 'processPayment')]
    public function auditPaymentCompliance(PaymentRequest $request): null
    {
        // Validate compliance rules before payment is processed
        return null;
    }

    /**
     * Runs after processPayment() — receives result then original arguments.
     */
    #[After(method: 'processPayment')]
    public function logPaymentOutcome(mixed $result, PaymentRequest $request): mixed
    {
        // Record the outcome for auditing
        return $result;
    }
}
```

The `method:` parameter accepts the exact target method name. The plugin method name can be anything.

## Plugin Execution Order

When multiple plugins target the same method:

1. All `before` plugins run (in sort order)
2. The original method runs (unless a before plugin short-circuited)
3. All `after` plugins run (in sort order)

### After Plugin Result Chaining

When multiple after plugins target the same method, each plugin's return value becomes the next plugin's `$result` input. The plugins form a chain — the final result is whatever the last after plugin returns.

```php
// Target method returns: 10

#[After(sortOrder: 10)]
public function double(int $result): int
{
    return $result * 2; // 10 → 20
}

#[After(sortOrder: 20)]
public function addBonus(int $result): int
{
    return $result + 5; // 20 → 25
}

// Final result: 25
```

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
