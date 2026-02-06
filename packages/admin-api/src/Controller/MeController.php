<?php

declare(strict_types=1);

namespace Marko\AdminApi\Controller;

use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class MeController
{
    public function __construct(
        private readonly GuardInterface $guard,
    ) {}

    #[Get('/admin/api/v1/me')]
    public function me(): Response
    {
        $user = $this->guard->user();

        if (!$user instanceof AdminUserInterface) {
            return ApiResponse::unauthorized();
        }

        $roles = array_map(
            static fn ($role): array => [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'slug' => $role->getSlug(),
            ],
            $user->getRoles(),
        );

        return ApiResponse::success(data: [
            'id' => $user->getAuthIdentifier(),
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $roles,
            'permissions' => $user->getPermissionKeys(),
        ]);
    }
}
