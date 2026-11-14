<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Marko\Database\Entity\Cast\CastInterface;
use Marko\Database\Entity\Cast\CastResolver;
use Marko\Database\Entity\Cast\DateTimeCast;
use Marko\Database\Entity\Cast\EncryptedCast;
use Marko\Database\Entity\Cast\EnumCast;
use Marko\Database\Entity\Cast\EquatableCastInterface;
use Marko\Database\Entity\Cast\JsonCast;
use Marko\Database\Entity\Cast\ScalarCast;
use Marko\Database\Exceptions\EntityException;
use Marko\Encryption\Exceptions\EncryptionException;
use ReflectionClass;
use WeakMap;

/**
 * Hydrates entity objects from database rows and extracts entity data for persistence.
 */
class EntityHydrator
{
    /**
     * Stores original values for entities for dirty checking.
     *
     * @var WeakMap<Entity, array<string, mixed>>
     */
    private WeakMap $originalValues;

    /**
     * Stores the (unencrypted) database representation of cast and encrypted properties,
     * so in-place mutation of mutable value objects can be detected.
     *
     * @var WeakMap<Entity, array<string, mixed>>
     */
    private WeakMap $originalDatabaseValues;

    private CastResolver $castResolver;

    public function __construct(
        private readonly ?EntityMetadataFactory $metadataFactory = null,
        ?CastResolver $castResolver = null,
    ) {
        $this->originalValues = new WeakMap();
        $this->originalDatabaseValues = new WeakMap();
        $this->castResolver = $castResolver ?? new CastResolver();
    }

    /**
     * Hydrate an entity from a database row.
     *
     * @template T of Entity
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $row Database row with column names as keys
     * @return T
     *
     * @throws EntityException
     */
    public function hydrate(
        string $entityClass,
        array $row,
        EntityMetadata $metadata,
    ): Entity {
        $reflection = new ReflectionClass($entityClass);
        $entity = $reflection->newInstanceWithoutConstructor();

        $originalValues = [];
        $originalDatabaseValues = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            $columnName = $propMeta->columnName;

            if (!array_key_exists($columnName, $row)) {
                continue;
            }

            $dbValue = $row[$columnName];

            try {
                $phpValue = $this->toPhpValue($dbValue, $propMeta);
            } catch (EncryptionException $e) {
                throw EntityException::decryptionFailed($entityClass, $propName, $columnName, $e);
            }

            $property = $reflection->getProperty($propName);
            $property->setValue($entity, $phpValue);

            $originalValues[$propName] = $phpValue;

            if ($this->tracksDatabaseValue($propMeta)) {
                $originalDatabaseValues[$propName] = $this->castOnlyDatabaseValue($phpValue, $propMeta);
            }
        }

        $this->originalValues[$entity] = $originalValues;
        $this->originalDatabaseValues[$entity] = $originalDatabaseValues;

        if ($metadata->extenders !== []) {
            if ($this->metadataFactory === null) {
                throw EntityException::hydratorRequiresMetadataFactory($entityClass);
            }

            foreach ($metadata->extenders as $extenderClass) {
                $extenderMetadata = $this->metadataFactory->parse($extenderClass);

                // Silently skip extenders whose columns are entirely absent from the row
                $allPresent = array_all(
                    $extenderMetadata->properties,
                    fn (PropertyMetadata $propMeta) => array_key_exists($propMeta->columnName, $row),
                );

                if (!$allPresent) {
                    continue;
                }

                $extenderReflection = new ReflectionClass($extenderClass);
                $companion = $extenderReflection->newInstanceWithoutConstructor();
                $companionOriginalValues = [];
                $companionDatabaseValues = [];

                foreach ($extenderMetadata->properties as $propName => $propMeta) {
                    $columnName = $propMeta->columnName;

                    if (!array_key_exists($columnName, $row)) {
                        continue;
                    }

                    $dbValue = $row[$columnName];

                    try {
                        $phpValue = $this->toPhpValue($dbValue, $propMeta);
                    } catch (EncryptionException $e) {
                        throw EntityException::decryptionFailed($extenderClass, $propName, $columnName, $e);
                    }

                    if (
                        $phpValue === null
                        && !$propMeta->nullable
                    ) {
                        continue;
                    }

                    $property = $extenderReflection->getProperty($propName);
                    $property->setValue($companion, $phpValue);

                    $companionOriginalValues[$propName] = $phpValue;

                    if ($this->tracksDatabaseValue($propMeta)) {
                        $companionDatabaseValues[$propName] = $this->castOnlyDatabaseValue($phpValue, $propMeta);
                    }
                }

                $this->originalValues[$companion] = $companionOriginalValues;
                $this->originalDatabaseValues[$companion] = $companionDatabaseValues;
                $this->attachCompanion($entity, $companion);
            }
        }

        return $entity;
    }

    /**
     * Extract entity data to a row array for persistence.
     *
     * An uninitialized primary key is left out of the row, so the caller can
     * let the database generate it or report that it was never set.
     *
     * @return array<string, mixed> Column name => value
     *
     * @throws EntityException
     */
    public function extract(
        Entity $entity,
        EntityMetadata $metadata,
    ): array {
        $reflection = new ReflectionClass($entity);
        $row = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            $property = $reflection->getProperty($propName);

            if (
                $propMeta->isPrimaryKey
                && !$property->isInitialized($entity)
            ) {
                continue;
            }

            $value = $property->getValue($entity);

            $row[$propMeta->columnName] = $this->toDatabaseValue($value, $propMeta);
        }

        return $row;
    }

    /**
     * Extract parent entity columns plus all attached companion columns into a single row array.
     *
     * Calls extract() for the parent, then extract() for each attached companion using
     * the companion's own metadata (fetched on-demand via the factory). The existing
     * extract() signature is unchanged — new behavior lives here.
     *
     * @return array<string, mixed> Merged column name => value for parent + all companions
     *
     * @throws EntityException
     */
    public function extractAll(
        Entity $parent,
        EntityMetadata $parentMetadata,
    ): array {
        $row = $this->extract($parent, $parentMetadata);

        foreach ($parent->companions() as $companion) {
            if ($this->metadataFactory === null) {
                throw EntityException::hydratorRequiresMetadataFactory($parentMetadata->entityClass);
            }

            $companionMetadata = $this->metadataFactory->parse($companion::class);
            $row = array_merge($row, $this->extract($companion, $companionMetadata));
        }

        return $row;
    }

    /**
     * Check if an entity is new (not yet persisted).
     */
    public function isNew(
        Entity $entity,
        EntityMetadata $metadata,
    ): bool {
        $pkProperty = $metadata->getPrimaryKeyProperty();

        if ($pkProperty === null) {
            return true;
        }

        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty($pkProperty->name);

        if (!$property->isInitialized($entity)) {
            return true;
        }

        $value = $property->getValue($entity);

        // For auto-increment PKs, a null value means the entity has never been
        // persisted (the DB assigns the ID on INSERT).
        if ($pkProperty->isAutoIncrement) {
            return $value === null;
        }

        // For non-auto-increment PKs (e.g. client-supplied UUIDs), a set PK value
        // does NOT imply the entity was ever persisted. Use originalValues tracking:
        // if no snapshot exists, the entity has never passed through hydrate() or
        // registerOriginalValues(), so treat it as new.
        return !isset($this->originalValues[$entity]);
    }

    /**
     * Snapshot an entity's current property values into the originalValues WeakMap.
     *
     * Enables dirty-checking for entities that never passed through hydrate(),
     * such as freshly inserted entities. Idempotent — overwrites any prior snapshot.
     *
     * @throws EntityException
     */
    public function registerOriginalValues(
        Entity $entity,
        EntityMetadata $metadata,
    ): void {
        $reflection = new ReflectionClass($entity);
        $values = [];
        $databaseValues = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            $property = $reflection->getProperty($propName);

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $values[$propName] = $property->getValue($entity);

            if ($this->tracksDatabaseValue($propMeta)) {
                $databaseValues[$propName] = $this->castOnlyDatabaseValue($values[$propName], $propMeta);
            }
        }

        $this->originalValues[$entity] = $values;
        $this->originalDatabaseValues[$entity] = $databaseValues;
    }

    /**
     * Attach a companion to a parent entity (package-internal, used during hydration).
     *
     * Delegates into the shared EntityCompanionStorage so companions set here
     * are visible through Entity::companions() / Entity::companion().
     */
    public function attachCompanion(
        Entity $entity,
        Entity $companion,
    ): void {
        EntityCompanionStorage::instance()->attach($entity, $companion);
    }

    /**
     * Get the original values for an entity (values when it was hydrated).
     *
     * @return array<string, mixed> Property name => original value
     */
    public function getOriginalValues(
        Entity $entity,
    ): array {
        return $this->originalValues[$entity] ?? [];
    }

    /**
     * Check if the entity has any changed properties.
     *
     * @throws EntityException
     */
    public function isDirty(
        Entity $entity,
        EntityMetadata $metadata,
    ): bool {
        return count($this->getDirtyProperties($entity, $metadata)) > 0;
    }

    /**
     * Get the list of property names that have changed.
     *
     * @return array<string>
     *
     * @throws EntityException
     */
    public function getDirtyProperties(
        Entity $entity,
        EntityMetadata $metadata,
    ): array {
        $originalValues = $this->originalValues[$entity] ?? [];
        $originalDatabaseValues = $this->originalDatabaseValues[$entity] ?? [];
        $reflection = new ReflectionClass($entity);
        $dirty = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            if (!array_key_exists($propName, $originalValues)) {
                continue;
            }

            $property = $reflection->getProperty($propName);

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $currentValue = $property->getValue($entity);
            $originalValue = $originalValues[$propName];

            $changed = array_key_exists($propName, $originalDatabaseValues)
                ? !$this->trackedValueUnchanged(
                    $propMeta,
                    $currentValue,
                    $originalValue,
                    $originalDatabaseValues[$propName],
                )
                : !$this->valuesEqual($propMeta, $currentValue, $originalValue);

            if ($changed) {
                $dirty[] = $propName;
            }
        }

        return $dirty;
    }

    /**
     * Convert a database value to the PHP value assigned to the property.
     *
     * This is the single read path: every hydrated value goes through here.
     *
     * @throws EntityException|EncryptionException
     */
    public function toPhpValue(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if ($meta->encrypted) {
            $value = $this->castResolver->resolve(EncryptedCast::class)->toPhp($value, $meta);
        }

        $cast = $this->castFor($meta);

        return $cast !== null ? $cast->toPhp($value, $meta) : $value;
    }

    /**
     * Convert a PHP property value to the value bound in INSERT and UPDATE statements.
     *
     * This is the single write path: inserts, batch inserts and updates all go through here.
     *
     * @throws EntityException|EncryptionException
     */
    public function toDatabaseValue(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        if ($value === null) {
            return null;
        }

        $cast = $this->castFor($meta);
        $dbValue = $cast !== null ? $cast->toDatabase($value, $meta) : $value;

        if (
            $meta->encrypted
            && $dbValue !== null
        ) {
            return $this->castResolver->resolve(EncryptedCast::class)->toDatabase($dbValue, $meta);
        }

        return $dbValue;
    }

    /**
     * Whether a property's database representation is snapshotted for dirty checking.
     */
    private function tracksDatabaseValue(
        PropertyMetadata $meta,
    ): bool {
        return $meta->castClass !== null
            || $meta->encrypted;
    }

    /**
     * The database representation of a PHP value produced by the cast alone (before encryption).
     *
     * @throws EntityException
     */
    private function castOnlyDatabaseValue(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        if ($value === null) {
            return null;
        }

        $cast = $this->castFor($meta);

        return $cast !== null ? $cast->toDatabase($value, $meta) : $value;
    }

    /**
     * Compare a tracked property against its snapshot.
     *
     * Equatable casts decide via equals(); otherwise the current value's database
     * representation is compared with the snapshot, which also catches in-place
     * mutation of mutable value objects.
     *
     * @throws EntityException
     */
    private function trackedValueUnchanged(
        PropertyMetadata $meta,
        mixed $current,
        mixed $originalPhp,
        mixed $originalDatabase,
    ): bool {
        if (
            $current === null
            || $originalPhp === null
        ) {
            return $current === $originalPhp;
        }

        $cast = $this->castFor($meta);

        if ($cast instanceof EquatableCastInterface) {
            return $current === $originalPhp
                || $cast->equals($current, $originalPhp, $meta);
        }

        return $this->castOnlyDatabaseValue($current, $meta) === $originalDatabase;
    }

    /**
     * Resolve the cast for a property: its #[Cast] class, or the built-in cast for its type.
     *
     * @throws EntityException
     */
    private function castFor(
        PropertyMetadata $meta,
    ): ?CastInterface {
        $castClass = $meta->castClass ?? $this->builtInCastClass($meta);

        return $castClass !== null ? $this->castResolver->resolve($castClass) : null;
    }

    /**
     * @return class-string<CastInterface>|null
     */
    private function builtInCastClass(
        PropertyMetadata $meta,
    ): ?string {
        if (
            $meta->columnType === 'json'
            || (
                $meta->columnType === null
                && $meta->type === 'array'
            )
        ) {
            return JsonCast::class;
        }

        if ($meta->enumClass !== null) {
            return EnumCast::class;
        }

        if ($meta->type === DateTimeImmutable::class) {
            return DateTimeCast::class;
        }

        return match ($meta->type) {
            'int', 'float', 'bool', 'string' => ScalarCast::class,
            default => null,
        };
    }

    /**
     * Compare two PHP values of a property for dirty checking.
     *
     * Identical values are equal. Otherwise a cast implementing EquatableCastInterface
     * decides; objects handled by any other cast are equal when their database
     * representations are identical, so an unchanged value object is never dirty.
     *
     * @throws EntityException
     */
    private function valuesEqual(
        PropertyMetadata $meta,
        mixed $a,
        mixed $b,
    ): bool {
        if ($a === $b) {
            return true;
        }

        if (
            $a === null
            || $b === null
        ) {
            return false;
        }

        $cast = $this->castFor($meta);

        if ($cast instanceof EquatableCastInterface) {
            return $cast->equals($a, $b, $meta);
        }

        if (
            $cast !== null
            && (
                is_object($a)
                || is_object($b)
            )
        ) {
            return $cast->toDatabase($a, $meta) === $cast->toDatabase($b, $meta);
        }

        if (
            $a instanceof DateTimeInterface
            && $b instanceof DateTimeInterface
        ) {
            return $a->getTimestamp() === $b->getTimestamp();
        }

        return false;
    }
}
