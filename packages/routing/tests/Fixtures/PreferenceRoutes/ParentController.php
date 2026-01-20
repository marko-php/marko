<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

use Marko\Routing\Attributes\Get;

class ParentController
{
    #[Get('/posts')]
    public function index(): void {}

    #[Get('/posts/{id}')]
    public function show(): void {}
}
