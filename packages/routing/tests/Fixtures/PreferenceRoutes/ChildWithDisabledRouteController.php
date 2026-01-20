<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

use Marko\Routing\Attributes\DisableRoute;

/**
 * Child controller that overrides a parent method with DisableRoute.
 * Should remove the parent's route.
 */
class ChildWithDisabledRouteController extends ParentController
{
    #[DisableRoute]
    public function index(): void {}
}
