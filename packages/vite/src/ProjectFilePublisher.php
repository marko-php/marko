<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Core\Path\ProjectPaths;
use Marko\Vite\ValueObjects\FilePublishResult;

class ProjectFilePublisher
{
    public function __construct(
        private readonly ProjectPaths $paths,
    ) {}

    public function publish(
        string $relativePath,
        string $contents,
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        $absolutePath = $this->paths->base . '/' . $relativePath;
        $exists = is_file($absolutePath);

        if ($exists && !$force) {
            return new FilePublishResult($relativePath, 'skipped');
        }

        if ($dryRun) {
            return new FilePublishResult($relativePath, $exists ? 'would_replace' : 'would_create');
        }

        $directory = dirname($absolutePath);
        if (! is_dir($directory) && ! $this->safeMkdir($directory) && ! is_dir($directory)) {
            return new FilePublishResult(
                $relativePath,
                'failed',
                sprintf('Could not create directory `%s`', $directory),
            );
        }

        if (! $this->safeWrite($absolutePath, $contents)) {
            return new FilePublishResult(
                $relativePath,
                'failed',
                sprintf('Could not write `%s`', $absolutePath),
            );
        }

        return new FilePublishResult($relativePath, $exists ? 'replaced' : 'created');
    }

    private function safeMkdir(string $directory): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return mkdir($directory, 0777, true);
        } finally {
            restore_error_handler();
        }
    }

    private function safeWrite(
        string $path,
        string $contents,
    ): bool {
        set_error_handler(static fn (): bool => true);

        try {
            return file_put_contents($path, $contents) !== false;
        } finally {
            restore_error_handler();
        }
    }
}
