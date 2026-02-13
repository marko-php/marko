<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\NonExistentPackage\Attributes\SomeAttribute;
use Marko\Routing\Attributes\Get;

class MissingAttributeController
{
    #[Get('/admin/dashboard')]
    #[SomeAttribute('test')]
    public function index(): void {}
}
