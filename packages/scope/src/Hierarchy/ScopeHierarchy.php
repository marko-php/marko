<?php

declare(strict_types=1);

namespace Marko\Scope\Hierarchy;

use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Exceptions\UnknownScopeException;

readonly class ScopeHierarchy
{
    /** @var array<string, bool> */
    private array $pathMap;

    /** @var list<string> */
    private array $paths;

    /**
     * @param list<string> $paths
     * @throws ScopeConfigurationException
     */
    public function __construct(array $paths = [])
    {
        $map = [];
        foreach ($paths as $path) {
            if (isset($map[$path])) {
                throw ScopeConfigurationException::duplicatePath($path);
            }
            $map[$path] = true;
        }
        $this->pathMap = $map;
        $this->paths = $paths;
    }

    /**
     * Build a ScopeHierarchy from a flat list of dotted paths.
     *
     * @param list<string> $paths
     * @throws ScopeConfigurationException
     */
    public static function fromPaths(array $paths): self
    {
        return new self($paths);
    }

    /**
     * Return all declared paths in declaration order.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Determine whether $ancestor is an ancestor of $descendant.
     * A path is not an ancestor of itself.
     */
    public function isAncestor(
        string $ancestor,
        string $descendant,
    ): bool
    {
        return str_starts_with($descendant, $ancestor . '.');
    }

    /**
     * Check whether a path is declared in this hierarchy.
     */
    public function exists(string $path): bool
    {
        return isset($this->pathMap[$path]);
    }

    /**
     * Walk up from a path to the root, returning the path and all ancestors.
     *
     * @return list<string>
     * @throws UnknownScopeException
     */
    public function walkUp(string $path): array
    {
        if (!$this->exists($path)) {
            throw UnknownScopeException::forAxisAndPath('', $path);
        }

        $result = [$path];
        $current = $path;

        while (($dotPos = strrpos($current, '.')) !== false) {
            $current = substr($current, 0, $dotPos);
            $result[] = $current;
        }

        return $result;
    }
}
