<?php

declare(strict_types=1);

namespace Marko\Inertia\Interfaces;

interface ComponentResolverInterface
{
    /**
     * Resolve a component name to its absolute file path.
     *
     * @param string $component Component name (e.g. 'blog::Dashboard/Index')
     * @return string Absolute path to component file
     */
    public function resolve(string $component): string;

    /**
     * Determine whether a component exists in any configured search path.
     *
     * @param string $component Component name
     */
    public function exists(string $component): bool;

    /**
     * Get all paths that were searched for a component.
     *
     * @param string $component Component name
     * @return array<string> List of searched paths
     */
    public function getSearchedPaths(string $component): array;
}
