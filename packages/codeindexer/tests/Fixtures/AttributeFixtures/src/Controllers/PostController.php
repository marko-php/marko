<?php

declare(strict_types=1);

namespace Fixture\AttributeFixtures\Controllers;

use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Patch;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Put;

class PostController
{
    #[Get('/posts')]
    public function index(): void {}

    #[Get('/posts/{id}')]
    #[Middleware('Fixture\AttributeFixtures\Middleware\AuthMiddleware')]
    public function show(): void {}

    #[Post('/posts')]
    public function store(): void {}

    #[Put('/posts/{id}')]
    public function update(): void {}

    #[Patch('/posts/{id}')]
    public function patch(): void {}

    #[Delete('/posts/{id}')]
    public function destroy(): void {}

    #[DisableRoute]
    public function disabled(): void {}
}
