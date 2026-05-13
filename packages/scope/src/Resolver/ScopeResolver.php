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
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Resolution\ScopeWalker;
use Marko\Scope\Scope;
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
     * @throws ScopeContextException
     * @throws UnknownAxisException
     */
    public function resolved(
        Entity $entity,
        string $property,
    ): mixed
    {
        $entityClass = get_class($entity);

        if (!property_exists($entity, $property)) {
            throw ScopeContextException::unknownProperty($entityClass, $property);
        }

        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);
        $axes = $scopeMetadata->axesForProperty($property);
        $registry = $this->getRegistry();

        $companion = $this->findCompanion($entity);

        if ($companion !== null) {
            $result = $this->scopeWalker->walk($companion, $property, $axes, $this->scopeContext, $registry);

            if ($result->isFound()) {
                return $result->value();
            }
        }

        return $entity->{$property};
    }

    /**
     * @throws UnknownAxisException
     */
    public function resolvedAt(
        Entity $entity,
        string $property,
        Scope $scope,
    ): mixed
    {
        $entityClass = get_class($entity);
        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);
        $axes = $scopeMetadata->axesForProperty($property);
        $registry = $this->getRegistry();

        $companion = $this->findCompanion($entity);

        if ($companion !== null) {
            $result = $this->scopeWalker->walkAt($companion, $property, $axes, $scope, $registry);

            if ($result->isFound()) {
                return $result->value();
            }
        }

        return $entity->{$property};
    }

    /**
     * @throws ScopeContextException
     * @throws UnknownAxisException
     * @throws EntityException
     * @throws MissingPrimaryKeyException
     */
    public function setOverride(
        Entity $entity,
        string $property,
        mixed $value,
        Scope $scope,
    ): void
    {
        $entityClass = get_class($entity);
        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);

        if (!$scopeMetadata->isScoped($property)) {
            throw ScopeContextException::propertyNotScoped($property, $entityClass);
        }

        $companion = $this->findCompanion($entity);

        if ($companion === null) {
            $companion = $this->createCompanion($entityClass);
            $entity->attachCompanion($companion);
        }

        $scopeKey = $scope->axisName . ':' . $scope->path;
        $companion->setOverride($scopeKey, $property, $value);
    }

    /**
     * @throws ScopeContextException
     * @throws UnknownAxisException
     */
    public function clearOverride(
        Entity $entity,
        string $property,
        Scope $scope,
    ): void
    {
        $entityClass = get_class($entity);
        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);

        if (!$scopeMetadata->isScoped($property)) {
            throw ScopeContextException::propertyNotScoped($property, $entityClass);
        }

        $companion = $this->findCompanion($entity);

        if ($companion === null) {
            return;
        }

        $scopeKey = $scope->axisName . ':' . $scope->path;
        $companion->clearOverride($scopeKey, $property);
    }

    /**
     * @param class-string $entityClass
     * @throws ScopeContextException
     * @throws EntityException
     * @throws MissingPrimaryKeyException
     */
    private function createCompanion(string $entityClass): ScopedOverridesEntity
    {
        $entityMetadata = $this->entityMetadataFactory->parse($entityClass);

        foreach ($entityMetadata->extenders as $extender) {
            if (is_subclass_of($extender, ScopedOverridesEntity::class)) {
                return new $extender();
            }
        }

        throw new ScopeContextException(
            message: "No ScopedOverridesEntity subclass found for '$entityClass'",
            context: "Creating override companion for '$entityClass'",
            suggestion: "Register a class extending ScopedOverridesEntity with #[Table(extends: $entityClass::class)]",
        );
    }

    private function findCompanion(Entity $entity): ?ScopedOverridesEntity
    {
        foreach ($entity->companions() as $companion) {
            if ($companion instanceof ScopedOverridesEntity) {
                return $companion;
            }
        }

        return null;
    }

    private function getRegistry(): ScopeRegistryInterface
    {
        return $this->scopeContext->registry();
    }
}
