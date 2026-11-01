<?php

declare(strict_types=1);

namespace Marko\View\Attributes;

use Attribute;

/**
 * Marks a ViewDataProviderInterface implementation for discovery.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ViewData {}
