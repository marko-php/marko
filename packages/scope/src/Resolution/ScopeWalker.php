<?php

declare(strict_types=1);

namespace Marko\Scope\Resolution;

use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Exceptions\UnknownScopeException;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Scope;
use Marko\Scope\Storage\ScopedOverridesEntity;

class ScopeWalker
{
    /**
     * @param list<string> $axes
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function walk(
        ScopedOverridesEntity $overrides,
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

            $hierarchy = $registry->getHierarchy($axis);
            $walked = $hierarchy->walkUp($path);

            foreach ($walked as $scope) {
                $scopeKey = $axis . ':' . $scope;

                if ($overrides->hasOverride($scopeKey, $property)) {
                    return ScopeWalkResult::found($overrides->getOverride($scopeKey, $property));
                }
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
        ScopedOverridesEntity $overrides,
        string $property,
        array $axes,
        Scope $scope,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        $axis = $scope->axisName;

        if (!in_array($axis, $axes, true)) {
            return ScopeWalkResult::notFound();
        }

        $hierarchy = $registry->getHierarchy($axis);
        $walked = $hierarchy->walkUp($scope->path);

        foreach ($walked as $scopePath) {
            $scopeKey = $axis . ':' . $scopePath;

            if ($overrides->hasOverride($scopeKey, $property)) {
                return ScopeWalkResult::found($overrides->getOverride($scopeKey, $property));
            }
        }

        return ScopeWalkResult::notFound();
    }
}
