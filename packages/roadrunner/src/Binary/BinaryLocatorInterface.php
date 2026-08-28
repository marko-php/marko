<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Binary;

/**
 * Locates the RoadRunner (`rr`) binary so it can be launched by the serve command.
 */
interface BinaryLocatorInterface
{
    /**
     * Returns the absolute path to the RoadRunner binary, or null if it cannot be found.
     */
    public function locate(): ?string;
}
