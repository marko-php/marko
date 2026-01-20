<?php

declare(strict_types=1);

namespace Marko\Core\Module;

interface ModuleRepositoryInterface
{
    /**
     * Get all discovered modules.
     *
     * @return array<ModuleManifest>
     */
    public function all(): array;
}
