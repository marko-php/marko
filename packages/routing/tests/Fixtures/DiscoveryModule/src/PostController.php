<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Post;

class PostController
{
    #[Post('/posts')]
    public function store(): void {}
}
