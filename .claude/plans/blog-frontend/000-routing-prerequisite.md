# Prerequisite: RoutePrefix Attribute in marko/routing

**Status**: prerequisite
**Package**: marko/routing (not marko/blog)

## Description
The blog module's configurable route prefix feature requires the `marko/routing` package to support a `#[RoutePrefix]` attribute that can resolve its value from configuration at route discovery time.

## Why This Is Needed
PHP attributes must have constant expressions as values. You cannot write:
```php
#[Get($this->config->getRoutePrefix() . '/')]  // Invalid PHP
```

The solution is a controller-level attribute that declares the config key:
```php
#[RoutePrefix(configKey: 'blog.route_prefix', default: '/blog')]
class PostController
{
    #[Get('/')]  // Resolved to /blog/ (or custom prefix) at registration
}
```

## Implementation in marko/routing

### 1. Create the RoutePrefix Attribute

```php
// packages/routing/src/Attributes/RoutePrefix.php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RoutePrefix
{
    public function __construct(
        public readonly ?string $prefix = null,
        public readonly ?string $configKey = null,
        public readonly string $default = '',
    ) {}
}
```

### 2. Update Route Discovery

The `RouteDiscovery` class (or equivalent) needs to:

1. Check for `#[RoutePrefix]` on the controller class
2. If `configKey` is set, resolve the value from `ConfigRepositoryInterface`
3. If `prefix` is set directly, use that value
4. Prepend the resolved prefix to all route paths in that controller

```php
// Pseudocode for route discovery
class RouteDiscovery
{
    public function __construct(
        private readonly ConfigRepositoryInterface $config,
    ) {}

    public function discoverRoutes(string $controllerClass): array
    {
        $reflection = new ReflectionClass($controllerClass);
        $routes = [];

        // Check for RoutePrefix attribute
        $prefix = $this->resolvePrefix($reflection);

        foreach ($reflection->getMethods() as $method) {
            foreach ($this->getRouteAttributes($method) as $routeAttribute) {
                $routes[] = new Route(
                    path: $prefix . $routeAttribute->path,
                    method: $routeAttribute->method,
                    controller: $controllerClass,
                    action: $method->getName(),
                );
            }
        }

        return $routes;
    }

    private function resolvePrefix(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(RoutePrefix::class);

        if (empty($attributes)) {
            return '';
        }

        $routePrefix = $attributes[0]->newInstance();

        // Direct prefix takes precedence
        if ($routePrefix->prefix !== null) {
            return $routePrefix->prefix;
        }

        // Resolve from config
        if ($routePrefix->configKey !== null) {
            return $this->config->get(
                $routePrefix->configKey,
                $routePrefix->default,
            );
        }

        return $routePrefix->default;
    }
}
```

### 3. Preference/Override Behavior

When a controller is replaced via `#[Preference]`, the child class can:

1. **Inherit parent's RoutePrefix** - If child has no `#[RoutePrefix]`, parent's prefix applies
2. **Override with new RoutePrefix** - Child can declare its own `#[RoutePrefix]`
3. **Override with different config key** - Child can point to different config

```php
// Original controller
#[RoutePrefix(configKey: 'blog.route_prefix', default: '/blog')]
class PostController { ... }

// Custom controller via Preference - inherits /blog prefix
#[Preference(for: PostController::class)]
class CustomPostController extends PostController { ... }

// Custom controller with different prefix
#[Preference(for: PostController::class)]
#[RoutePrefix(prefix: '/articles')]  // Hardcoded override
class ArticleController extends PostController { ... }

// Custom controller with different config
#[Preference(for: PostController::class)]
#[RoutePrefix(configKey: 'myapp.article_prefix', default: '/articles')]
class ArticleController extends PostController { ... }
```

### 4. Plugin Behavior

Plugins work at the class/method level, unaffected by route prefix:

```php
#[Plugin(target: PostController::class)]
class PostControllerPlugin
{
    // This hooks PostController::index regardless of URL
    #[Before]
    public function beforeIndex(): void { ... }
}
```

### 5. Documentation for marko/routing README

Add a section to the routing package README:

```markdown
## Configurable Route Prefixes

Use `#[RoutePrefix]` to group routes under a configurable path prefix.

### Static Prefix
```php
#[RoutePrefix(prefix: '/admin')]
class AdminController
{
    #[Get('/dashboard')]  // /admin/dashboard
    public function dashboard(): Response
}
```

### Config-Based Prefix
```php
#[RoutePrefix(configKey: 'blog.route_prefix', default: '/blog')]
class PostController
{
    #[Get('/')]           // Uses config value, e.g., /blog/
    #[Get('/{slug}')]     // Uses config value, e.g., /blog/{slug}
}
```

### Overriding via Preference
When replacing a controller, you can:
- Inherit the parent's prefix (no attribute needed)
- Override with a new static prefix
- Override with a different config key

### Priority
1. Direct `prefix` value (if set)
2. Value from `configKey` (resolved from ConfigRepositoryInterface)
3. `default` value (if config key not found)
```

## Acceptance Criteria
- [ ] `#[RoutePrefix]` attribute created in marko/routing
- [ ] Route discovery resolves prefix from config when configKey specified
- [ ] Direct prefix value takes precedence over configKey
- [ ] Child controllers inherit parent prefix when not overridden
- [ ] Child controllers can override prefix via their own attribute
- [ ] Plugins continue to work regardless of prefix changes
- [ ] Documentation added to marko/routing README

## Note
This prerequisite should be completed before or in parallel with the blog module work. The blog module's controller tasks (024-031) depend on this feature being available.
