<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

use Marko\Routing\Attributes\Get;

/**
 * Child controller that overrides a parent method WITH a Route attribute.
 * Should use the child's route definition.
 */
class ChildWithOwnRouteController extends ParentController
{
    #[Get('/custom-posts')]
    public function index(): void {}
}
