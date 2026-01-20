<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

#[Middleware(['ClassMiddleware'])]
class MiddlewareController
{
    #[Get('/with-middleware')]
    #[Middleware(['MethodMiddleware'])]
    public function withMiddleware(): void {}

    #[Get('/class-only')]
    public function classOnly(): void {}
}
