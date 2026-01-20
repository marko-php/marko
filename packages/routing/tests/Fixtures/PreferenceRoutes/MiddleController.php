<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

use Marko\Routing\Attributes\Get;

/**
 * Middle level controller that overrides one method from grandparent.
 */
class MiddleController extends GrandparentController
{
    #[Get('/custom-articles/{id}')]
    public function view(): void {}
}
