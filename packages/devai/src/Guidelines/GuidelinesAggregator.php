<?php

declare(strict_types=1);

namespace Marko\DevAi\Guidelines;

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;

readonly class GuidelinesAggregator
{
    private const string GUIDELINES_REL_PATH = '/resources/ai/guidelines.md';

    public function __construct(
        private ModuleWalkerInterface $walker,
        private string $devaiPackageRoot,
    ) {}

    /**
     * @return array<string, string> packageName => guidelines markdown
     */
    public function aggregate(): array
    {
        $guidelines = [];

        // Always include core guidelines from devai's own resources
        $coreGuidelinesPath = $this->devaiPackageRoot . '/resources/ai/guidelines/core.md';
        if (is_file($coreGuidelinesPath)) {
            $guidelines['marko/core'] = (string) file_get_contents($coreGuidelinesPath);
        }

        // Discover and aggregate per-package guidelines
        foreach ($this->walker->walk() as $module) {
            $path = $module->path . self::GUIDELINES_REL_PATH;
            if (is_file($path)) {
                $guidelines[$module->name] = (string) file_get_contents($path);
            }
        }

        // Sort deterministically: core first, then alphabetical
        $sorted = [];
        if (isset($guidelines['marko/core'])) {
            $sorted['marko/core'] = $guidelines['marko/core'];
            unset($guidelines['marko/core']);
        }
        ksort($guidelines);

        return $sorted + $guidelines;
    }
}
