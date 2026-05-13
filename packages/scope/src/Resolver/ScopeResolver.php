<?php

declare(strict_types=1);

namespace Marko\Scope\Resolver;

use Marko\Database\Entity\Entity;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\ScopeContextException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Exceptions\UnknownScopeException;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Resolution\ScopeWalker;
use Marko\Scope\Scope;
use Marko\Scope\Storage\HasScopesInterface;

readonly class ScopeResolver
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeWalker $scopeWalker,
        private ScopeContext $scopeContext,
    ) {}

    /**
     * @throws ScopeContextException|UnknownAxisException|UnknownScopeException
     */
    public function resolved(
        Entity $entity,
        string $property,
    ): mixed {
        $entityClass = get_class($entity);

        if (!property_exists($entity, $property)) {
            throw ScopeContextException::unknownProperty($entityClass, $property);
        }

        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);
        $axes = $scopeMetadata->axesForProperty($property);
        $registry = $this->getRegistry();

        $storage = $this->findStorage($entity);

        if ($storage !== null) {
            $result = $this->scopeWalker->walk($storage, $property, $axes, $this->scopeContext, $registry);

            if ($result->isFound()) {
                return $result->value();
            }
        }

        return $entity->{$property};
    }

    /**
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function resolvedAt(
        Entity $entity,
        string $property,
        Scope $scope,
    ): mixed {
        $entityClass = get_class($entity);
        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);
        $axes = $scopeMetadata->axesForProperty($property);
        $registry = $this->getRegistry();

        $storage = $this->findStorage($entity);

        if ($storage !== null) {
            $result = $this->scopeWalker->walkAt($storage, $property, $axes, $scope, $registry);

            if ($result->isFound()) {
                return $result->value();
            }
        }

        return $entity->{$property};
    }

    /**
     * @throws ScopeContextException|UnknownAxisException
     */
    public function setOverride(
        Entity $entity,
        string $property,
        mixed $value,
        Scope $scope,
    ): void {
        $entityClass = get_class($entity);
        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);

        if (!$scopeMetadata->isScoped($property)) {
            throw ScopeContextException::propertyNotScoped($property, $entityClass);
        }

        $storage = $this->findStorage($entity);

        if ($storage === null) {
            throw new ScopeContextException(
                message: "Entity '$entityClass' has no scope storage: it must implement HasScopesInterface or have a companion that does.",
                context: "Setting scope override for property '$property' on '$entityClass'",
                suggestion: "Add 'use HasScopes; implements HasScopesInterface;' to '$entityClass', or register and attach a companion class that implements HasScopesInterface.",
            );
        }

        $scopeKey = $scope->axisName . ':' . $scope->path;
        $storage->setOverride($scopeKey, $property, $value);
    }

    /**
     * @throws ScopeContextException|UnknownAxisException
     */
    public function clearOverride(
        Entity $entity,
        string $property,
        Scope $scope,
    ): void {
        $entityClass = get_class($entity);
        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);

        if (!$scopeMetadata->isScoped($property)) {
            throw ScopeContextException::propertyNotScoped($property, $entityClass);
        }

        $storage = $this->findStorage($entity);

        if ($storage === null) {
            return;
        }

        $scopeKey = $scope->axisName . ':' . $scope->path;
        $storage->clearOverride($scopeKey, $property);
    }

    private function findStorage(Entity $entity): ?HasScopesInterface
    {
        if ($entity instanceof HasScopesInterface) {
            return $entity;
        }

        return array_find(
            $entity->companions(),
            fn ($companion) => $companion instanceof HasScopesInterface,
        );
    }

    private function getRegistry(): ScopeRegistryInterface
    {
        return $this->scopeContext->registry();
    }
}
