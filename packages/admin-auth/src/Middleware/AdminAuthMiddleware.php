<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Middleware;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Gates admin routes on an authenticated admin user and, when the matched
 * action or its controller class carries #[RequiresPermission], on that
 * permission. A method-level attribute replaces a class-level one.
 *
 * The user comes from the admin guard (admin-auth.guard, resolved through
 * AuthManager), never the app's default guard, so a frontend login is not an
 * admin login. A user that guard authenticates that is not an
 * AdminUserInterface gets a 403 on every admin route, permission-gated or not.
 *
 * An unauthenticated request gets a 401 UnauthenticatedException. The one
 * exception is a browser request on a stateful guard: it is redirected to
 * `{prefix}/login`. A stateless guard (StatelessGuardInterface, e.g. the token
 * guard) never redirects, since its API clients cannot follow a login
 * redirect, and its 401 carries the guard's WWW-Authenticate challenge. A
 * request that wants JSON (Request::wantsJson()) never redirects either,
 * whatever the guard. A user without the required permission gets a 403
 * HttpException. The routing pipeline renders both through ExceptionRenderer.
 * The required permission is only put in the exception's context for logs,
 * never in the client-facing message.
 */
readonly class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AdminGuardResolver $adminGuard,
        private AdminConfigInterface $adminConfig,
        private PermissionRegistryInterface $permissionRegistry,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException|HttpException|ReflectionException|UnauthenticatedException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $guard = $this->adminGuard->guard();

        if (!$guard->check()) {
            if (
                !$guard instanceof StatelessGuardInterface
                && !$request->wantsJson()
            ) {
                return Response::redirect($this->adminConfig->getRoutePrefix() . '/login');
            }

            throw UnauthenticatedException::forGuard($guard);
        }

        $user = $guard->user();

        if (!$user instanceof AdminUserInterface) {
            throw $this->forbidden(
                "The user authenticated on guard '{$guard->getName()}' is not an admin user.",
            );
        }

        $requiredPermission = $this->getRequiredPermission($request);

        if (
            $requiredPermission !== null
            && !$this->userHasPermission($user, $requiredPermission)
        ) {
            throw $this->forbidden("Admin user lacks the required permission '$requiredPermission'.");
        }

        return $next($request);
    }

    /**
     * The context goes to logs only; the client sees the generic message.
     */
    private function forbidden(
        string $context,
    ): HttpException {
        return new HttpException(
            statusCode: 403,
            message: 'Forbidden.',
            context: $context,
        );
    }

    private function userHasPermission(
        AdminUserInterface $user,
        string $requiredPermission,
    ): bool {
        // Super admin bypass is handled by AdminUser::hasPermission()
        if ($user->hasPermission($requiredPermission)) {
            return true;
        }

        // Check wildcard patterns: iterate user's permission keys as patterns
        return array_any(
            $user->getPermissionKeys(),
            fn (string $permissionKey): bool => $this->permissionRegistry->matches(
                $permissionKey,
                $requiredPermission,
            ),
        );
    }

    /**
     * A method-level #[RequiresPermission] replaces a class-level one, the same
     * precedence #[Can] uses.
     *
     * @throws ReflectionException
     */
    private function getRequiredPermission(
        Request $request,
    ): ?string {
        $controller = $request->controller();
        $action = $request->action();

        if (
            $controller === null
            || $action === null
        ) {
            return null;
        }

        $attributes = new ReflectionMethod($controller, $action)->getAttributes(RequiresPermission::class);

        if ($attributes === []) {
            $attributes = new ReflectionClass($controller)->getAttributes(RequiresPermission::class);
        }

        return $attributes === [] ? null : $attributes[0]->newInstance()->permission;
    }
}
