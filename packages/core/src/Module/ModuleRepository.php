<?php

declare(strict_types=1);

namespace Marko\Core\Module;

readonly class ModuleRepository implements ModuleRepositoryInterface
{
    /**
     * @param array<ModuleManifest> $modules
     */
    public function __construct(
        private array $modules,
    ) {}

    public function all(): array
    {
        return $this->modules;
    }
}
