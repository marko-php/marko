<?php

declare(strict_types=1);

namespace Marko\Scope\Resolution;

use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Exceptions\UnknownScopeException;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Scope;
use Marko\Scope\Storage\HasScopesInterface;

class ScopeWalker
{
    /**
     * @param list<string> $axes
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function walk(
        HasScopesInterface $overrides,
        string $property,
        array $axes,
        ScopeContext $context,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        foreach ($axes as $axis) {
            $path = $context->get($axis);

            if ($path === null) {
                continue;
            }

            $result = $this->findFirstMatch($overrides, $property, $axis, $path, $registry);

            if ($result->isFound()) {
                return $result;
            }
        }

        return ScopeWalkResult::notFound();
    }

    /**
     * Walk overrides for a single explicit scope, ignoring any ambient ScopeContext.
     *
     * @param list<string> $axes
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function walkAt(
        HasScopesInterface $overrides,
        string $property,
        array $axes,
        Scope $scope,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        if (!in_array($scope->axisName, $axes, true)) {
            return ScopeWalkResult::notFound();
        }

        return $this->findFirstMatch($overrides, $property, $scope->axisName, $scope->path, $registry);
    }

    /**
     * @throws UnknownAxisException|UnknownScopeException
     */
    private function findFirstMatch(
        HasScopesInterface $overrides,
        string $property,
        string $axis,
        string $path,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        $walked = $registry->getHierarchy($axis)->walkUp($path);

        $matchedScope = array_find(
            $walked,
            fn (string $scopePath) => $overrides->hasOverride($axis . ':' . $scopePath, $property),
        );

        return $matchedScope !== null
            ? ScopeWalkResult::found($overrides->override($axis . ':' . $matchedScope, $property))
            : ScopeWalkResult::notFound();
    }
}
