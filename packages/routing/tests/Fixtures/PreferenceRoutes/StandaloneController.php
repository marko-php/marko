<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;

/**
 * A standalone controller with no parent class.
 */
class StandaloneController
{
    #[Get('/standalone')]
    public function index(): void {}

    #[Post('/standalone/create')]
    public function create(): void {}
}
