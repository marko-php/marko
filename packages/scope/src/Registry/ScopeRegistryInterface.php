<?php

declare(strict_types=1);

namespace Marko\Scope\Registry;

use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Hierarchy\ScopeHierarchy;

interface ScopeRegistryInterface
{
    public function hasAxis(string $name): bool;

    /**
     * @throws UnknownAxisException
     */
    public function getAxis(string $name): ScopeAxis;

    /**
     * @return list<string>
     */
    public function listAxes(): array;

    /**
     * @throws UnknownAxisException
     */
    public function getHierarchy(string $axisName): ScopeHierarchy;
}
