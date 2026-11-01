---
title: marko/authorization
description: Gates, policies, and the #[Can] attribute -- control who can do what with expressive, testable authorization checks.
---

Gates, policies, and the `#[Can]` attribute --- control who can do what with expressive, testable authorization checks. Define abilities with closures via the Gate, or organize them into policy classes mapped to entities. Use `#[Can]` on controllers or their methods to enforce permissions through middleware that runs on every route once the package is installed. Gate hooks answer every check first (an admin bypass in one class), and every decision is dispatched as an event for auditing. Denials throw `AuthorizationException` with clear context.

## Installation

```bash
composer require marko/authorization
```

## Usage

### Defining Abilities

Register abilities as closures on the Gate:

```php
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;

readonly class AuthorizationBootstrap
{
    public function __construct(
        private GateInterface $gate,
    ) {}

    public function boot(): void
    {
        $this->gate->define(
            'edit-settings',
            fn (?AuthorizableInterface $user) => $user?->can('admin', true) ?? false,
        );
    }
}
```

### Checking Abilities

```php
use Marko\Authorization\Contracts\GateInterface;

readonly class SettingsController
{
    public function __construct(
        private GateInterface $gate,
    ) {}

    public function update(): void
    {
        if ($this->gate->denies('edit-settings')) {
            // handle unauthorized
        }

        // proceed with update
    }
}
```

Use `authorize()` to throw on denial:

```php
$this->gate->authorize('edit-settings');
// Throws AuthorizationException if denied
```

You don't need to catch it in a controller. `AuthorizationException` implements `HttpExceptionInterface`, so the routing pipeline turns an uncaught denial into a `403` (see [Failure Responses](#failure-responses)).

### Using Policies

Policies group authorization logic per entity. Create a policy class with methods named after abilities:

```php
use Marko\Authorization\AuthorizableInterface;

class PostPolicy
{
    public function update(
        ?AuthorizableInterface $user,
        Post $post,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $post->authorId;
    }

    public function delete(
        ?AuthorizableInterface $user,
        Post $post,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $post->authorId;
    }
}
```

Register the policy:

```php
$this->gate->policy(
    Post::class,
    PostPolicy::class,
);
```

Check against the policy by passing the entity:

```php
$this->gate->authorize('update', $post);
```

### How the Gate Decides

Every check runs the same steps in this order. The first step that gives an answer decides:

1. **Token scope.** When the authorization guard is the token guard, the user's API token must grant the ability. A token scoped to `['read']` is denied `delete`, and nothing below can change that (see [API Token Abilities](#api-token-abilities)).
2. **Gate hooks.** Each `#[GateHook]` class, highest priority first.
3. **Closures** registered with `define()`.
4. **The policy** for the first argument: its `before()` method if it implements `PolicyBeforeInterface`, then the ability method.
5. **Default deny.** Nothing defined the ability, so it is denied.

### Gate Hooks (Admin Bypass)

A gate hook answers a check before any closure or policy runs. Use one when a rule applies to every ability, such as "admins can do everything". Your policies then only describe the normal rules.

Implement `GateHookInterface` and mark the class with `#[GateHook]`. Hooks are discovered at boot from every module's `src/` directory, like `#[Observer]` classes. They're built through the container, so they can inject dependencies:

```php
use Marko\Authorization\Attributes\GateHook;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateHookInterface;

#[GateHook(priority: 100)]
class AdminBypass implements GateHookInterface
{
    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool {
        // null means "no opinion": the next hook, closure or policy decides
        return $user instanceof User && $user->isAdmin() ? true : null;
    }
}
```

`before()` returns:

- `true` to allow the ability
- `false` to deny it
- `null` to fall through to the next hook, then to closures and policies

Hooks run highest `priority` first (the default is `0`), and the first non-null result wins. `$user` is `null` for a guest, so handle guests explicitly. `$arguments` holds the arguments passed to the check, such as the entity.

A few rules:

- A hook can't widen a scoped API token. An admin whose token only grants `read` is still denied `delete`, because the token scope is checked first.
- Hooks apply to closures and policies alike. A hook that returns `true` allows an ability even when the policy has no method for it.
- A hook never hides a mistake. If every hook returns `null` and the policy has no method for the ability, the check still throws `PolicyException`.
- There are no "after" hooks that change a result. To audit decisions, observe [`AbilityCheckedEvent`](#auditing-ability-checks) instead.

### Policy-Level Before

A policy can implement `PolicyBeforeInterface` to run a check for its own abilities only. It runs after every gate hook returned `null` and before the ability method, with the same `true`/`false`/`null` meaning:

```php
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\PolicyBeforeInterface;

class SoundPolicy implements PolicyBeforeInterface
{
    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool {
        // Default-library sounds are never editable here
        return $arguments[0] instanceof Sound && $arguments[0]->isDefaultLibrary ? false : null;
    }

    public function update(
        ?AuthorizableInterface $user,
        Sound $sound,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $sound->ownerId;
    }
}
```

`before()` only runs for abilities the policy has a method for. Checking a missing ability still throws `PolicyException`.

Policies are created with `new`, without dependency injection. A policy-level `before()` can only use the user and the arguments. Use a gate hook when the check needs a service.

### Auditing Ability Checks

After every decision the Gate dispatches `Marko\Authorization\Event\AbilityCheckedEvent`. Observers can see the decision but can't change it. The event carries:

- `user`: the user, or `null` for a guest
- `ability` and `arguments`: what was checked
- `result`: the decision
- `source`: an `AbilityDecisionSource` case (`TokenScope`, `GateHook`, `PolicyBefore`, `Closure`, `Policy` or `DefaultDeny`)
- `decidedBy`: the hook or policy class that decided, or `null` for the token scope, a closure or the default deny

Observe it like any other event:

```php
use Marko\Authorization\Event\AbilityCheckedEvent;
use Marko\Authorization\Event\AbilityDecisionSource;
use Marko\Core\Attributes\Observer;
use Marko\Log\Contracts\LoggerInterface;

#[Observer(event: AbilityCheckedEvent::class)]
readonly class LogAdminBypass
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(
        AbilityCheckedEvent $event,
    ): void {
        if ($event->source === AbilityDecisionSource::GateHook) {
            $this->logger->info("'$event->ability' decided by $event->decidedBy");
        }
    }
}
```

A check that throws `PolicyException` dispatches no event.

### The #[Can] Attribute

Add `#[Can]` to a controller method to require an ability before the action runs:

```php
use Marko\Authorization\Attributes\Can;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Http\Response;

class PostController
{
    #[Get('/admin/stats')]
    #[Can('view-stats')]
    public function stats(): Response
    {
        // Only reachable if the gate allows 'view-stats'
    }

    #[PostRoute('/posts')]
    #[Can(ability: 'create', entityClass: Post::class)]
    public function store(): Response
    {
        // Only reachable if PostPolicy::create() allows it
    }
}
```

Installing `marko/authorization` registers `AuthorizationMiddleware` as global middleware, so you don't attach it to routes yourself. For every matched route it reads `#[Can]` from the controller action and checks the Gate:

- **No `#[Can]`**: the request passes through untouched.
- **Not logged in**: throws `UnauthenticatedException` (from `marko/authentication`), which renders as `401 Unauthorized`. When the guard is stateless, such as the [token guard](/docs/packages/authentication-token/), the `401` carries the guard's `WWW-Authenticate` challenge (`WWW-Authenticate: Bearer`), exactly as `AuthMiddleware` sends it.
- **Logged in but denied**: throws `AuthorizationException`, which renders as `403 Forbidden`.

The middleware never builds these responses itself; see [Failure Responses](#failure-responses).

The middleware checks authentication with the same guard the Gate uses, so the guard named by `authorization.default_guard` decides whether the request gets a `401`. If that value is `null`, the authentication default guard is used.

Don't also list `AuthorizationMiddleware` in a route's `middleware` array. It already runs globally, so it would only check the same ability a second time.

#### API Token Abilities

When the authorization guard implements `Marko\Authentication\Contracts\AbilityScopedGuardInterface`, as the [token guard](/docs/packages/authentication-token/#checking-token-abilities) does, the Gate also asks the guard whether the credential grants the ability. For an authenticated user, `allows()`, `denies()`, `authorize()` and `#[Can]` deny any ability the token does not grant, before any gate closure or policy runs. A token can only narrow its user's authority, never widen it:

```php
// Token issued with abilities: ['posts:read']
$gate->allows('posts:read');   // true, if the closure or policy also allows it
$gate->allows('posts:delete'); // false, whatever the closure or policy says
```

A token issued with no abilities (`[]`) or with `'*'` grants every ability, so only the closure or policy decides. Guests are unaffected: closures and policies that allow a `null` user still run. Session guards do not implement the interface, so nothing changes for them.

#### Cost on Routes Without `#[Can]`

The middleware builds the Gate and the guard lazily, the first time it sees a route with `#[Can]`. For a route without `#[Can]`, and for a request that matches no route (`404`/`405`), `AuthorizationMiddleware` never resolves the Gate, the `AuthManager`, the guard or the authorization config. Those requests cost one cached attribute lookup, and they work even when authentication isn't configured. You can install `marko/authorization` just to call the Gate from a CLI command or a service without setting up the authentication stack. (An installed session driver still runs its own `SessionMiddleware` on every request; that is independent of `#[Can]`.)

A route with `#[Can]` needs the full stack:

- An authentication guard: `authorization.default_guard`, or the authentication default guard when that value is `null`, must name a guard defined under `authentication.guards`.
- A `UserProviderInterface` binding, so the guard can load the logged-in user.
- A session driver (such as `marko/session-file` or `marko/session-database`) when that guard is a session guard.
- A `TokenRepositoryInterface` binding when that guard is the `token` guard from [`marko/authentication-token`](/docs/packages/authentication-token/).

The middleware never needs this stack for routes without `#[Can]`, but the boot checks it when any route uses `#[Can]`, so a broken setup fails before the first user reaches the route:

- **Live boots** (the `development` environment, or no discovery cache): after every module's `boot` callback has run, `marko/authorization` lists the routes that carry `#[Can]` and keep `AuthorizationMiddleware`. If there are any, it builds the guard once (`authorization.default_guard`, else `authentication.default.guard`). A guard name missing from `authentication.guards`, such as a typo in either setting, fails here too, because `AuthManager` throws for an undefined guard instead of building a session guard. When that fails, the boot throws `Marko\Authorization\Exceptions\AuthorizationConfigurationException`. Its message names the guard and the underlying error, its context lists up to five `#[Can]` routes, and the original exception is kept as `getPrevious()`. The check runs after all boot callbacks (on the core `ApplicationBooted` event), so a guard driver that any module registers in its `boot` callback is already there.
- **`marko discovery:cache`**: the command always boots live, so it fails with the same exception and writes no cache. A broken setup never reaches a cached deploy.
- **Cached boots** (production, from the discovery cache) skip the check entirely: no route reflection, no guard. Under PHP-FPM every request is a boot, so the check would otherwise put back the per-request cost that lazy building avoids. The cache was compiled by a live boot that passed the check.

The check is skipped when no route uses `#[Can]`, and for a `#[Can]` route that excludes `AuthorizationMiddleware` with `#[WithoutMiddleware]`. Apps that use the Gate only from services or commands still boot without any authentication configuration. Every live boot runs the check, CLI commands included, so in development a broken setup also stops commands such as `marko discovery:clear` until it is fixed.

What remains at request time: the Gate is still built lazily on the first `#[Can]` request, so a guard swapped in after boot (as the HTTP test client's `actingAs()` does) is the one it authorizes with. If the environment changes after the cache was compiled (for example a binding that now fails), a cached boot doesn't notice. The first request to a `#[Can]` route then fails with the container or config error that names what is missing, while routes without `#[Can]` keep working. A failed build is not cached, so the next `#[Can]` request tries again. The Gate and guard are built once per middleware instance, so a long-running worker builds them on its first `#[Can]` request and reuses them after that.

#### Class-Level `#[Can]`

Put `#[Can]` on the controller class to protect every action in it. A `#[Can]` on a method replaces the class-level one for that action:

```php
use Marko\Authorization\Attributes\Can;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

#[Can('admin.access')]
class AdminController
{
    #[Get('/admin')]
    public function dashboard(): Response
    {
        // Requires 'admin.access'
    }

    #[Get('/admin/reports')]
    #[Can('admin.reports')]
    public function reports(): Response
    {
        // Requires 'admin.reports' only
    }
}
```

PHP attributes are not inherited, so a class-level `#[Can]` applies only to the class that declares it, not to its subclasses. Repeat it on a subclass that needs the same protection.

#### Ordering with Sessions

The guard reads the logged-in user from the session, so authorization runs after the session middleware. `marko/authorization` declares `sequence.after` on `marko/session-file` and `marko/session-database`, the session drivers that register `SessionMiddleware` globally. Drivers that aren't installed are ignored.

#### Class-String vs Instance Checks

With `entityClass`, `#[Can]` passes the class name (for example `Post::class`) to the Gate, not an entity. The Gate resolves the policy from the class name, so this fits abilities that don't need a specific record, such as `create` or `viewAny`:

```php
use Marko\Authorization\AuthorizableInterface;

class PostPolicy
{
    public function create(
        ?AuthorizableInterface $user,
        string $postClass,
    ): bool {
        return $user !== null;
    }
}
```

Abilities that depend on a specific record, such as `update` or `delete` on one post, need the loaded entity. Marko has no route model binding (explicit over implicit), so load the entity in the controller and call `authorize()` yourself:

```php
use Marko\Authorization\Contracts\GateInterface;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Response;

readonly class PostController
{
    public function __construct(
        private GateInterface $gate,
        private PostRepository $posts,
    ) {}

    #[Put('/posts/{id}')]
    public function update(
        int $id,
    ): Response {
        $post = $this->posts->find($id);
        $this->gate->authorize('update', $post);

        // proceed with update
    }
}
```

### Failure Responses

Authorization failures are thrown as exceptions that implement `Marko\Core\Exceptions\HttpExceptionInterface`. The routing pipeline renders them through [`ExceptionRenderer`](/docs/packages/routing/#errors-and-http-exceptions), in the same way as every other HTTP error:

| Failure | Thrown by | Exception | Status |
|---|---|---|---|
| `#[Can]` route, no authenticated user | `AuthorizationMiddleware` | `Marko\Authentication\Exceptions\UnauthenticatedException` (an `HttpException`) | `401`, with the guard's `WWW-Authenticate` challenge when the guard is [stateless](/docs/packages/authentication/#stateless-guards) |
| `#[Can]` route, ability denied | `AuthorizationMiddleware` | `Marko\Authorization\Exceptions\AuthorizationException` | `403` |
| `$gate->authorize()` denied in a controller or service | `Gate` | `Marko\Authorization\Exceptions\AuthorizationException` | `403` |

The renderer picks the format from the request. It sends JSON when the `Accept` header asks for `application/json` or any `+json` type (such as `application/vnd.api+json`), or when the request has a JSON `Content-Type` and no `Accept` header. Otherwise it sends a minimal HTML page:

```json
{"message": "Unauthorized."}
{"message": "Forbidden."}
```

The `403` body never contains the ability or the resource name, because they can leak internals. Both stay on the exception for logging, through `getAbility()` and `getResource()`.

A `Gate` has no notion of a guest. A denied `authorize()` call is always a `403`, even when nobody is logged in. Use `#[Can]` (or [`AuthMiddleware`](/docs/packages/authentication/#authmiddleware)) when a guest should get a `401`.

To render branded error pages or a different JSON shape, replace `ExceptionRenderer` with a `#[Preference]`. See [Custom error pages](/docs/packages/routing/#custom-error-pages) in the routing docs. Authorization failures go through it like any other HTTP error, so nothing authorization-specific is needed.

Mistakes in policy setup are a different case: registering two policies for one entity, or checking an ability the policy has no method for. These throw `PolicyException`, which does **not** implement `HttpExceptionInterface`. It reaches your error handler as a `500` with the full message, instead of being hidden behind a `403`.

### Implementing AuthorizableInterface

Your user entity must implement `AuthorizableInterface`, which extends [`AuthenticatableInterface`](/docs/packages/authentication/):

```php
use Marko\Authorization\AuthorizableInterface;

class User implements AuthorizableInterface
{
    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool {
        // Check ability via gate or custom logic
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash;
    }
}
```

## API Reference

### GateInterface

```php
interface GateInterface
{
    public function define(string $ability, callable $callback): void;
    public function allows(string $ability, mixed ...$arguments): bool;
    public function denies(string $ability, mixed ...$arguments): bool;
    public function authorize(string $ability, mixed ...$arguments): bool;
    public function policy(string $entityClass, string $policyClass): void;
}
```

### GateHookInterface and #[GateHook]

```php
#[GateHook(priority: 0)]  // Targets classes; higher priority runs first

interface GateHookInterface
{
    public function before(?AuthorizableInterface $user, string $ability, array $arguments): ?bool;
}
```

`Marko\Authorization\GateHookRegistry::hooks()` returns the discovered hooks in run order. The `marko discovery:cache` command stores them under the `gate_hooks` key, so a cached boot skips the scan. A `#[GateHook]` class that doesn't implement `GateHookInterface` throws `GateHookException` at discovery.

### PolicyBeforeInterface

```php
interface PolicyBeforeInterface
{
    public function before(?AuthorizableInterface $user, string $ability, array $arguments): ?bool;
}
```

### AbilityCheckedEvent

```php
class AbilityCheckedEvent extends Event
{
    public readonly ?AuthorizableInterface $user;
    public readonly string $ability;
    public readonly array $arguments;
    public readonly bool $result;
    public readonly AbilityDecisionSource $source;
    public readonly ?string $decidedBy;
}

enum AbilityDecisionSource: string
{
    case TokenScope = 'token_scope';
    case GateHook = 'gate_hook';
    case PolicyBefore = 'policy_before';
    case Closure = 'closure';
    case Policy = 'policy';
    case DefaultDeny = 'default_deny';
}
```

### AuthorizableInterface

```php
interface AuthorizableInterface extends AuthenticatableInterface
{
    public function can(string $ability, mixed ...$arguments): bool;
}
```

### #[Can] Attribute

```php
#[Can(ability: 'edit', entityClass: Post::class)]  // With entity
#[Can(ability: 'access-dashboard')]                  // Without entity
```

Targets classes and methods. A method-level `#[Can]` overrides a class-level one.

### AuthorizationMiddleware

Registered as global middleware by `module.php`. It reads the matched controller and action from the request. The constructor takes factories, not instances, so the Gate and guard are built only on the first route with `#[Can]`.

```php
class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @param Closure(): GateInterface $gate
     * @param Closure(): GuardInterface $guard
     */
    public function __construct(
        Closure $gate,
        Closure $guard,
        CanAttributeReader $canAttributeReader = new CanAttributeReader(),
    );
    public function handle(Request $request, callable $next): Response;
}
```

`Marko\Authorization\Routing\CanAttributeReader::read(string $controller, string $action): ?Can` returns the `#[Can]` that protects an action (the method's, else the class's). The middleware and the boot check share it.

### AuthorizationException

Rendered as `403` with `{"message": "Forbidden."}`. The ability and resource are never sent to the client.

```php
class AuthorizationException extends MarkoException implements HttpExceptionInterface
{
    public function __construct(
        string $message = 'Forbidden',
        string $ability = '',
        string $resource = '',
        string $context = '',
        string $suggestion = '',
        ?Throwable $previous = null,
    );

    public function getAbility(): string;
    public function getResource(): string;
    public function getStatusCode(): int;          // 403
    public function getHeaders(): array;           // []
    public function getResponseData(): array;      // ['message' => 'Forbidden.']

    public static function forbidden(string $ability, string $resource): self;
}
```

### PolicyException

Thrown for policy misconfiguration. It is not an HTTP exception, so it surfaces as a `500`.

```php
class PolicyException extends MarkoException
{
    public static function duplicatePolicy(string $entityClass, string $policyClass, string $existing): self;
    public static function missingMethod(string $policyClass, string $ability): self;
}
```

### AuthorizationConfigurationException

Thrown at boot when routes use `#[Can]` but the guard can't be built (see [Cost on Routes Without `#[Can]`](#cost-on-routes-without-can)). It is not an HTTP exception.

```php
class AuthorizationConfigurationException extends MarkoException
{
    /** @param array<int, string> $canRoutes "controller::action" keys */
    public static function cannotBuildForCan(?string $guard, array $canRoutes, Throwable $previous): self;
}
```
