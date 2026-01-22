<?php

declare(strict_types=1);

namespace Marko\Log\File\Rotation;

readonly class SizeRotation implements RotationStrategyInterface
{
    public function __construct(
        private int $maxSize = 10 * 1024 * 1024,
    ) {}

    public function getCurrentPath(
        string $basePath,
        string $channel,
    ): string {
        $basePath = rtrim($basePath, '/');
        $baseFile = "$basePath/$channel.log";

        // If file doesn't exist or is under max size, use it
        if (!file_exists($baseFile) || $this->getFileSize($baseFile) < $this->maxSize) {
            return $baseFile;
        }

        // Find the next available rotated file number
        $i = 1;

        while (file_exists("$basePath/$channel.$i.log") && $this->getFileSize(
            "$basePath/$channel.$i.log"
        ) >= $this->maxSize) {
            $i++;
        }

        return "$basePath/$channel.$i.log";
    }

    public function needsRotation(
        string $filePath,
    ): bool {
        if (!file_exists($filePath)) {
            return false;
        }

        return $this->getFileSize($filePath) >= $this->maxSize;
    }

    private function getFileSize(
        string $filePath,
    ): int {
        clearstatcache(true, $filePath);
        $size = filesize($filePath);

        return $size !== false ? $size : 0;
    }
}
