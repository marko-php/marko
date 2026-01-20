<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Patch;

class PatchController
{
    #[Patch('/posts/{id}')]
    public function patch(): void {}
}
