<?php

declare(strict_types=1);

namespace Marko\DevServer\Detection;

readonly class FrontendDetector
{
    public function __construct(
        private string $projectRoot,
    ) {}

    public function detect(): ?string
    {
        $packageJson = $this->projectRoot . '/package.json';

        if (!file_exists($packageJson)) {
            return null;
        }

        $data = json_decode(file_get_contents($packageJson), true);

        if (!isset($data['scripts']['dev'])) {
            return null;
        }

        return $this->buildCommand();
    }

    private function buildCommand(): string
    {
        if (file_exists($this->projectRoot . '/bun.lockb')) {
            return 'bun run dev';
        }

        if (file_exists($this->projectRoot . '/pnpm-lock.yaml')) {
            return 'pnpm run dev';
        }

        if (file_exists($this->projectRoot . '/yarn.lock')) {
            return 'yarn dev';
        }

        return 'npm run dev';
    }
}
