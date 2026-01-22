<?php

declare(strict_types=1);

namespace Marko\Log\File\Rotation;

interface RotationStrategyInterface
{
    /**
     * Get the current log file path based on the rotation strategy.
     */
    public function getCurrentPath(
        string $basePath,
        string $channel,
    ): string;

    /**
     * Check if rotation is needed for the given file.
     */
    public function needsRotation(
        string $filePath,
    ): bool;
}
