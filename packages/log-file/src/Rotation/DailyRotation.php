<?php

declare(strict_types=1);

namespace Marko\Log\File\Rotation;

use DateTimeImmutable;

readonly class DailyRotation implements RotationStrategyInterface
{
    public function __construct(
        private ?DateTimeImmutable $now = null,
    ) {}

    public function getCurrentPath(
        string $basePath,
        string $channel,
    ): string {
        $date = ($this->now ?? new DateTimeImmutable())->format('Y-m-d');

        return rtrim($basePath, '/') . "/$channel-$date.log";
    }

    public function needsRotation(
        string $filePath,
    ): bool {
        // Daily rotation happens automatically via date in filename
        // Check if current date file is different from the given file
        $directory = dirname($filePath);
        $filename = basename($filePath);

        // Extract channel from filename (channel-date.log)
        if (preg_match('/^(.+)-\d{4}-\d{2}-\d{2}\.log$/', $filename, $matches)) {
            $channel = $matches[1];
            $currentPath = $this->getCurrentPath($directory, $channel);

            return $filePath !== $currentPath;
        }

        return false;
    }
}
