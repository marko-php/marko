<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Put;

class MultiRouteController
{
    #[Get('/items')]
    public function index(): void {}

    #[Post('/items')]
    public function store(): void {}

    #[Get('/items/{id}')]
    public function show(): void {}

    #[Put('/items/{id}')]
    public function update(): void {}

    #[Delete('/items/{id}')]
    public function destroy(): void {}
}
