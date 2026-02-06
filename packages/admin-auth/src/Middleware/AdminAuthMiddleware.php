<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Middleware;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use ReflectionMethod;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly GuardInterface $guard,
        private readonly AdminConfigInterface $adminConfig,
        private readonly PermissionRegistryInterface $permissionRegistry,
        private readonly ?string $controller = null,
        private readonly ?string $action = null,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->guard->check()) {
            return $this->unauthorizedResponse($request);
        }

        $requiredPermission = $this->getRequiredPermission();

        if ($requiredPermission !== null) {
            $user = $this->guard->user();

            if (!$user instanceof AdminUserInterface || !$this->userHasPermission($user, $requiredPermission)) {
                return $this->forbiddenResponse($request);
            }
        }

        return $next($request);
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
        foreach ($user->getPermissionKeys() as $permissionKey) {
            if ($this->permissionRegistry->matches($permissionKey, $requiredPermission)) {
                return true;
            }
        }

        return false;
    }

    private function unauthorizedResponse(
        Request $request,
    ): Response {
        if ($this->isJsonRequest($request)) {
            return Response::json(
                data: ['error' => 'Unauthorized'],
                statusCode: 401,
            );
        }

        return Response::redirect($this->adminConfig->getRoutePrefix() . '/login');
    }

    private function forbiddenResponse(
        Request $request,
    ): Response {
        if ($this->isJsonRequest($request)) {
            return Response::json(
                data: ['error' => 'Forbidden'],
                statusCode: 403,
            );
        }

        return new Response(
            body: 'Forbidden',
            statusCode: 403,
        );
    }

    private function isJsonRequest(
        Request $request,
    ): bool {
        $accept = $request->header('Accept');

        return $accept !== null && str_contains($accept, 'application/json');
    }

    private function getRequiredPermission(): ?string
    {
        if ($this->controller === null || $this->action === null) {
            return null;
        }

        $reflection = new ReflectionMethod($this->controller, $this->action);
        $attributes = $reflection->getAttributes(RequiresPermission::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance()->permission;
    }
}
