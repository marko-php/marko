<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

/**
 * Child controller that overrides a parent method WITHOUT a Route attribute.
 * This is ambiguous - should the route be inherited or removed?
 * Should throw RouteException.
 */
class ChildWithAmbiguousOverrideController extends ParentController
{
    // Overrides index() but has no #[Route] or #[DisableRoute]
    public function index(): void {}
}
