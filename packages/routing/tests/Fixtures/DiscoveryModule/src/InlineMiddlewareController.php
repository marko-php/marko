<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;

#[Middleware(['ClassMiddleware'])]
class InlineMiddlewareController
{
    #[Get('/inline', middleware: ['InlineMiddleware'])]
    public function inlineOnly(): void {}

    #[Post('/inline-combined', middleware: ['InlineMiddleware'])]
    #[Middleware(['MethodMiddleware'])]
    public function combinedAll(): void {}
}
