<?php

declare(strict_types=1);

namespace Marko\Authorization\Middleware;

use Closure;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Exceptions\AuthorizationException;
use Marko\Authorization\Exceptions\PolicyException;
use Marko\Authorization\Routing\CanAttributeReader;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use ReflectionException;

/**
 * Enforces #[Can] on the matched controller action.
 *
 * Reads the matched route from the request (set by the Router before the
 * pipeline runs). A method-level #[Can] overrides a class-level one. Routes
 * without #[Can] pass straight through.
 *
 * The Gate and the guard are built lazily, the first time a route with
 * #[Can] is matched. Routes without #[Can] (and unmatched requests) never
 * construct them, so they cost nothing and need no authentication or
 * session configuration.
 *
 * Failures are thrown, never rendered here: a guest gets an
 * UnauthenticatedException (401), which carries the guard's WWW-Authenticate
 * challenge when the guard is stateless (e.g. the token guard), and a denied
 * user an AuthorizationException (403). The routing
 * pipeline renders both through ExceptionRenderer, with content negotiation
 * and any app-level renderer Preference.
 */
class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * Resolved #[Can] per "controller::action". Holds only immutable attribute
     * data, so it is safe to keep across requests in long-running workers.
     *
     * @var array<string, ?Can>
     */
    private array $resolved = [];

    private ?GateInterface $resolvedGate = null;

    private ?GuardInterface $resolvedGuard = null;

    /**
     * @param Closure(): GateInterface $gate Called once, on the first route with #[Can]
     * @param Closure(): GuardInterface $guard Called once, on the first route with #[Can]
     */
    public function __construct(
        private readonly Closure $gate,
        private readonly Closure $guard,
        private readonly CanAttributeReader $canAttributeReader = new CanAttributeReader(),
    ) {}

    /**
     * @throws AuthorizationException|PolicyException|ReflectionException|UnauthenticatedException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $canAttribute = $this->resolveCanAttribute($request);

        if ($canAttribute === null) {
            return $next($request);
        }

        $guard = $this->guard();

        if (!$guard->check()) {
            throw UnauthenticatedException::forGuard($guard);
        }

        $arguments = [];

        if ($canAttribute->entityClass !== null) {
            $arguments[] = $canAttribute->entityClass;
        }

        if ($this->gate()->allows($canAttribute->ability, ...$arguments)) {
            return $next($request);
        }

        throw AuthorizationException::forbidden(
            ability: $canAttribute->ability,
            resource: $canAttribute->entityClass ?? $request->controller() . '::' . $request->action(),
        );
    }

    private function gate(): GateInterface
    {
        return $this->resolvedGate ??= ($this->gate)();
    }

    private function guard(): GuardInterface
    {
        return $this->resolvedGuard ??= ($this->guard)();
    }

    /**
     * @throws ReflectionException
     */
    private function resolveCanAttribute(
        Request $request,
    ): ?Can {
        $controller = $request->controller();
        $action = $request->action();

        if (
            $controller === null
            || $action === null
        ) {
            return null;
        }

        $key = $controller . '::' . $action;

        if (!array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = $this->canAttributeReader->read($controller, $action);
        }

        return $this->resolved[$key];
    }
}
