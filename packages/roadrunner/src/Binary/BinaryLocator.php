<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Binary;

use Marko\Core\Path\ProjectPaths;

/**
 * Locates the `rr` binary by checking common project locations before falling
 * back to the shell PATH.
 */
readonly class BinaryLocator implements BinaryLocatorInterface
{
    public function __construct(
        private ProjectPaths $paths,
    ) {}

    public function locate(): ?string
    {
        foreach ($this->projectCandidates() as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        $fromPath = trim((string) shell_exec('command -v rr 2>/dev/null'));

        return $fromPath !== '' ? $fromPath : null;
    }

    /**
     * @return list<string>
     */
    private function projectCandidates(): array
    {
        return [
            $this->paths->base . '/rr',
            $this->paths->base . '/vendor/bin/rr',
        ];
    }
}
