<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Delete;

class DeleteController
{
    #[Delete('/posts/{id}')]
    public function destroy(): void {}
}
