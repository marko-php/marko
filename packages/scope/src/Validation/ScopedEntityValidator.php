<?php

declare(strict_types=1);

namespace Marko\Scope\Validation;

use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MissingPrimaryKeyException;
use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Storage\ScopedOverridesEntity;

/**
 * Boot-time validator that ensures every entity with scoped properties
 * has a registered ScopedOverridesEntity extender.
 */
readonly class ScopedEntityValidator
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private EntityMetadataFactory $entityMetadataFactory,
    ) {}

    /**
     * Validate that the given entity class has a proper overrides extender if it declares scoped properties.
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

        $entityMetadata = $this->entityMetadataFactory->parse($entityClass);
        $extenders = $entityMetadata->extenders;

        $scopedProperties = [];
        foreach ($scopeMetadata->scopedProperties() as $property) {
            $scopedProperties[$property] = $scopeMetadata->axesForProperty($property);
        }

        $hasValidExtender = false;
        foreach ($extenders as $extender) {
            if (is_subclass_of($extender, ScopedOverridesEntity::class)) {
                $hasValidExtender = true;
                break;
            }
        }

        if (count($extenders) === 0 || !$hasValidExtender) {
            if (count($extenders) > 0) {
                throw ScopeConfigurationException::wrongOverridesExtenderBase($entityClass, $extenders[0]);
            }

            throw ScopeConfigurationException::missingOverridesExtender($entityClass, $scopedProperties);
        }
    }
}
