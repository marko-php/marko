<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

/**
 * Grandchild controller that doesn't override any methods.
 * Should inherit grandparent's list() route and middle's view() route.
 */
class GrandchildController extends MiddleController
{
    // Does not override anything
}
