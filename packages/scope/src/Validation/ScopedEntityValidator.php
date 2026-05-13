<?php

declare(strict_types=1);

namespace Marko\Scope\Validation;

use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MissingPrimaryKeyException;
use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Storage\HasScopesInterface;

/**
 * Boot-time validator that ensures every entity with scoped properties
 * has scope storage configured — either via the HasScopes trait on the entity itself,
 * or via a registered companion class that implements HasScopesInterface.
 */
readonly class ScopedEntityValidator
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private EntityMetadataFactory $entityMetadataFactory,
    ) {}

    /**
     * Validate that the given entity class has proper scope storage if it declares scoped properties.
     *
     * @param class-string $entityClass
     *
     * @throws EntityException|MissingPrimaryKeyException|ScopeConfigurationException|UnknownAxisException
     */
    public function validate(string $entityClass): void
    {
        $scopeMetadata = $this->scopeMetadataFactory->for($entityClass);

        if (!$scopeMetadata->hasScopedProperties()) {
            return;
        }

        if (is_a($entityClass, HasScopesInterface::class, true)) {
            return;
        }

        $entityMetadata = $this->entityMetadataFactory->parse($entityClass);

        $hasCompatibleCompanion = array_any(
            $entityMetadata->extenders,
            fn (string $extender) => is_a($extender, HasScopesInterface::class, true),
        );

        if ($hasCompatibleCompanion) {
            return;
        }

        throw ScopeConfigurationException::missingScopesStorage($entityClass);
    }
}
