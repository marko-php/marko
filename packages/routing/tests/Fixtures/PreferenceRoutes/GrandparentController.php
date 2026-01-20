<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

use Marko\Routing\Attributes\Get;

class GrandparentController
{
    #[Get('/articles')]
    public function list(): void {}

    #[Get('/articles/{id}')]
    public function view(): void {}
}
