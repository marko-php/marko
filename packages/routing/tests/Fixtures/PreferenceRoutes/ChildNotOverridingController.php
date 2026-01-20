<?php

declare(strict_types=1);

namespace Test\PreferenceRoutes;

/**
 * Child controller that does NOT override any parent methods.
 * Should inherit all parent routes but use child class for dispatch.
 */
class ChildNotOverridingController extends ParentController
{
    // Does not override any parent methods - routes should be inherited
}
