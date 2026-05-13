<?php

declare(strict_types=1);

namespace Marko\Scope\Resolver;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MissingPrimaryKeyException;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\ScopeContextException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Exceptions\UnknownScopeException;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Resolution\ScopeWalker;
use Marko\Scope\Scope;
use Marko\Scope\Storage\HasScopesInterface;
use Marko\Scope\Storage\ScopedOverridesEntity;

readonly class ScopeResolver
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeWalker $scopeWalker,
        private ScopeContext $scopeContext,
        private EntityMetadataFactory $entityMetadataFactory,
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
     * @throws ScopeContextException|UnknownAxisException|EntityException|MissingPrimaryKeyException
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
            $companion = $this->createCompanion($entityClass);
            $entity->attachCompanion($companion);
            $storage = $companion;
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

    /**
     * @param class-string $entityClass
     * @throws ScopeContextException|EntityException|MissingPrimaryKeyException
     */
    private function createCompanion(string $entityClass): ScopedOverridesEntity
    {
        $entityMetadata = $this->entityMetadataFactory->parse($entityClass);

        $extender = array_find(
            $entityMetadata->extenders,
            fn (string $candidate) => is_subclass_of($candidate, ScopedOverridesEntity::class),
        );

        if ($extender !== null) {
            return new $extender();
        }

        throw new ScopeContextException(
            message: "No ScopedOverridesEntity subclass found for '$entityClass'",
            context: "Creating override companion for '$entityClass'",
            suggestion: "Register a class extending ScopedOverridesEntity with #[Table(extends: $entityClass::class)]",
        );
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
