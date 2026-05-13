<?php

declare(strict_types=1);

namespace Marko\Scope\Metadata;

use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Registry\ScopeRegistryInterface;
use ReflectionClass;

/**
 * Builds and caches ScopeMetadata for entity classes.
 */
class ScopeMetadataFactory
{
    /**
     * @var array<class-string, ScopeMetadata>
     */
    private array $cache = [];

    public function __construct(
        private readonly ScopeRegistryInterface $registry,
    ) {}

    /**
     * Build (or return cached) ScopeMetadata for the given class.
     *
     * @param class-string $entityClass
     *
     * @throws UnknownAxisException
     */
    public function for(string $entityClass): ScopeMetadata
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);
        $scopedProperties = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Scoped::class);

            if (count($attributes) === 0) {
                continue;
            }

            $scoped = $attributes[0]->newInstance();
            $axes = $scoped->axes;

            foreach ($axes as $axis) {
                if (!$this->registry->hasAxis($axis)) {
                    throw UnknownAxisException::forAxis($axis);
                }
            }

            $scopedProperties[$property->getName()] = $axes;
        }

        $metadata = new ScopeMetadata($scopedProperties);
        $this->cache[$entityClass] = $metadata;

        return $metadata;
    }
}
