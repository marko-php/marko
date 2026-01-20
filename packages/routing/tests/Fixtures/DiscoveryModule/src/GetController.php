<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Get;

class GetController
{
    #[Get('/posts')]
    public function index(): void {}
}
