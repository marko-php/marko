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

use App\Blog\Repository\PostRepository;
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
    public function find(
        mixed $result,
        int $id,
    ): mixed {
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
public function applyDiscount(
    float $price,
    int $quantity,
): null|array
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

Use the `sortOrder` parameter to control the order when multiple plugins target the same method. The default is `0`, and negative values are supported:

```php
#[Before(sortOrder: 10)]
public function getPost(int $id): null { /* runs first */ }
```

### Sort Order Conflicts

Marko never breaks a tie by load order. If two plugins hook the same target method with the same timing (`before` or `after`) and the same `sortOrder`, the application refuses to boot and throws a `PluginException` that names both plugins:

```text
Two plugins have conflicting sort order (0) for before hook on 'App\Blog\Repository\PostRepository::getPost()': App\Seo\Plugin\PostSeoPlugin and App\Cache\Plugin\PostCachePlugin
```

To fix it, give one of the plugins a different `sortOrder`. Because the default is `0`, two plugins that both leave it unset will conflict when they hook the same method. A `before` plugin and an `after` plugin with the same `sortOrder` don't conflict, because each timing is ordered separately.

## Advanced: Explicit Method Targeting

When you need both a `before` and an `after` interceptor for the same target method inside one plugin class, you cannot give two methods the same name. Use the `method:` parameter to specify the target method while giving each plugin method a distinct name.

```php title="OrderAuditPlugin.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use App\Commerce\Service\OrderService;
use App\Commerce\ValueObject\PaymentRequest;
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
    public function logPaymentOutcome(
        mixed $result,
        PaymentRequest $request,
    ): mixed {
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

## Receiving the Intercepted Instance

A plugin normally does not know which instance a call ran on. When one plugin intercepts several live instances of the same target (non-shared bindings such as several connections, cache stores or log channels), mark the **first** parameter of a `#[Before]` or `#[After]` method with `#[Subject]` and it receives the intercepted instance. The rest of the signature is unchanged: a before plugin then takes the target's arguments, and an after plugin takes `$result` and then the arguments. Plugins without `#[Subject]` are unaffected.

```php title="QueryLogPlugin.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Subject;
use Marko\Database\Connection\ConnectionInterface;

#[Plugin(target: ConnectionInterface::class)]
class QueryLogPlugin
{
    #[Before]
    public function query(
        #[Subject] ConnectionInterface $connection,
        string $sql,
        array $bindings = [],
    ): null {
        // $connection is the instance this call is running on
        return null;
    }

    #[After(method: 'query')]
    public function logQuery(
        #[Subject] ConnectionInterface $connection,
        array $result,
        string $sql,
        array $bindings = [],
    ): array {
        // $connection is the instance that ran the query
        return $result;
    }
}
```

A before plugin that returns an array still returns only the **target's** arguments. The subject is not part of it.

The subject is the object the application holds for that service: the interceptor the container returned, for interface targets and concrete-class targets alike. So `$subject === $injectedInstance` holds and its state is current. Calls a plugin makes on the subject go back through the plugin chain. A plugin must therefore not call the method it is hooked on, because that recurses. Calling other methods is fine, and those calls are intercepted like any other call.

For a singleton target you do not need `#[Subject]`: injecting the target's interface through the plugin's constructor still works.

### Subject Errors

Discovery throws a `PluginException` when `#[Subject]` is misused:

| Mistake | Message |
|---------|---------|
| Not on the first parameter (this includes two `#[Subject]` parameters) | `Parameter '$x' at position N of 'Plugin::method()' is marked #[Subject], but #[Subject] is only allowed on the first parameter` |
| On a variadic or by-reference parameter | `... is marked #[Subject], but #[Subject] cannot be variadic or passed by reference` |
| On a method that is neither `#[Before]` nor `#[After]` | `... is marked #[Subject], but 'method()' has neither #[Before] nor #[After], so the subject would never be passed` |
| Declared type cannot hold the target | `Parameter '$x' of 'Plugin::method()' is marked #[Subject] and declared as 'Type', which cannot hold the intercepted 'Target'` |

The declared type may be omitted, `object`, `mixed`, the target, one of its parent classes or interfaces, or a union containing one of those.

## Targeting Interfaces

Plugins can target interfaces, not just concrete classes. This is the **recommended approach** — it keeps your plugin decoupled from specific implementations and survives Preference swaps.

```php title="HasherPlugin.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Hashing\Contracts\HasherInterface;

#[Plugin(target: HasherInterface::class)]
class HasherPlugin
{
    #[Before]
    public function hash(string $value): null
    {
        // Runs before hash() on ANY HasherInterface implementation —
        // BcryptHasher, Argon2Hasher, or a custom one added via Preference.
        return null;
    }
}
```

When a plugin targets an interface, it fires regardless of which concrete implementation is resolved — whether via a binding, a Preference, or autowiring. This means a Preference swap from `BcryptHasher` to `Argon2Hasher` won't silently break your plugin.

:::tip[Interface vs concrete targeting]
- **Target the interface** when your plugin applies to the behavior contract (e.g., logging all hash operations)
- **Target the concrete class** when your plugin is specific to one implementation (e.g., tuning bcrypt cost)
:::

### Other interfaces of the target

The object the container returns for an interface plugin also implements the other interfaces of the concrete class behind it. Say a plugin targets `ConnectionInterface` (as `marko/debugbar` does) and the real connection also implements `TransactionInterface`. Code that checks `$connection instanceof TransactionInterface`, or injects `TransactionInterface`, still works.

Only methods of the plugged interface go through the plugin chain. Methods of the other interfaces are passed straight to the real object.

An interface is left out, and the wrapper then does not implement it, when any of these hold:

- it has plugins of its own
- it is a PHP built-in interface, or extends one
- it declares a property, a static method, a method that returns by reference, a method that returns `static`, a method whose name the interceptor reserves for itself, or a parameter whose default is an object
- its methods or constants clash with those of another interface the wrapper implements

### Constraints

- **Readonly classes cannot be targeted directly.** If a concrete class is `readonly`, target its interface instead. Marko will throw a helpful error explaining this.
- **Final classes and final methods cannot be targeted directly.** `final` forbids extending the class or overriding the method, which is how a concrete-class interceptor works. Marko refuses the plugin when it registers it, at boot, and names the plugin, class and method. Target an interface the class implements instead: the interface interceptor wraps the instance rather than extending it, so a final class is fine there.
- **One interface at a time.** If a class implements multiple interfaces and plugins are registered on more than one, Marko throws an error rather than silently picking a winner. Target the concrete class directly in this case.
- **Plugged interfaces are not carried across.** The wrapper is built for the interface the service was resolved through. An interface that has plugins of its own is not carried onto another interface's wrapper, so the wrapper does not implement it.
- **Intercepted methods cannot use object defaults.** The interceptor re-declares each intercepted method with the same default values, so a caller who omits an argument gets exactly the default you wrote. Scalars, arrays, enum cases and constants are all supported. A `new Foo()` default cannot be reproduced, so Marko throws a `PluginException` when it generates the interceptor. Use a nullable parameter instead: `?Foo $foo = null`, then `$foo ??= new Foo()` in the method body.

## How Plugins Work

When the container resolves a class or interface that has plugins registered, it generates an **interceptor class** at runtime. The interceptor wraps the real instance and routes method calls through the plugin chain.

### The interception flow

```
Container resolves HasherInterface
    → Finds BcryptHasher (via binding or Preference)
    → Checks: any plugins registered for HasherInterface?
    → Yes → Generates an interceptor class that implements HasherInterface
            (and the other interfaces of BcryptHasher)
    → Returns the interceptor (which wraps BcryptHasher internally)

Your code calls $hasher->hash('password')
    → Interceptor runs before plugins (in sort order)
    → Interceptor calls BcryptHasher::hash('password')
    → Interceptor runs after plugins (in sort order)
    → Returns the final result
```

### What gets generated

Interceptor classes are generated in memory (via `eval`) — no files are written to disk. Each generated class is cached for the duration of the request so generation only happens once per target.

The `eval` is not an injection point. Everything in the generated code comes from reflecting classes that are already installed: class, method and parameter names and types, plus default values written out with `var_export`. No request, user, database or config data reaches it, so changing what it evaluates means changing installed PHP code, which can already run anything. Hosts that block `eval` outright, for example with a hardening extension such as Snuffleupagus, cannot run plugins.

The strategy depends on the target:

| Target | Strategy |
|---|---|
| Interface (e.g., `HasherInterface`) | Generated class `implements` the interface and the other interfaces of the concrete class behind it, so `instanceof` checks on the plugged service keep working |
| Non-readonly, non-final concrete class | Generated class `extends` the concrete class --- works even when the constructor has mandatory promoted dependencies resolved via DI |
| Readonly concrete class | Error — target the interface instead |
| Final class, or a hook on a final method | Error at registration — target the interface instead |

In all cases, the generated class implements `PluginInterceptedInterface`, which provides `getPluginTarget()` for code that needs access to the underlying real instance. You rarely need it, because `instanceof` works on the returned object. For a concrete-class target, the returned object is the instance the interceptor was created from, not the live one: the interceptor itself holds the state. Do not read state from it.

### Zero overhead for non-plugged classes

If no plugins are registered for a class, the container returns the real instance directly — no interceptor, no wrapping, no overhead.

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
