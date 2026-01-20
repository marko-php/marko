<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * Marks a method as inheriting its route from the parent class.
 *
 * Use this when overriding a method in a child class (especially a Preference)
 * and you want to keep the parent's route configuration.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class InheritRoute {}
