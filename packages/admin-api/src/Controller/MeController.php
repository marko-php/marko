<?php

declare(strict_types=1);

namespace Marko\AdminApi\Controller;

use JsonException;
use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
readonly class MeController
{
    public function __construct(
        private GuardInterface $guard,
    ) {}

    /**
     * @throws JsonException
     */
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
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $roles,
            'permissions' => $user->getPermissionKeys(),
        ]);
    }
}
