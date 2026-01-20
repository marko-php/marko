<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Put;

class PutController
{
    #[Put('/posts/{id}')]
    public function update(): void {}
}
