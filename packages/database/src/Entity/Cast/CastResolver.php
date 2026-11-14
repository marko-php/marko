<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Exceptions\EntityException;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Builds and caches cast instances.
 *
 * With a container, casts are resolved through it, so they can declare constructor
 * dependencies and be replaced with a Preference. Without one (plain unit usage),
 * only casts whose constructor needs no arguments can be built.
 */
class CastResolver
{
    /**
     * @var array<class-string, CastInterface>
     */
    private array $casts = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null,
    ) {}

    /**
     * @param class-string $castClass
     *
     * @throws EntityException|ReflectionException
     */
    public function resolve(
        string $castClass,
    ): CastInterface {
        if (isset($this->casts[$castClass])) {
            return $this->casts[$castClass];
        }

        if (!is_a($castClass, CastInterface::class, true)) {
            throw EntityException::castClassInvalid($castClass, "Resolving cast '$castClass'");
        }

        $cast = $this->container !== null
            ? $this->resolveFromContainer($castClass)
            : $this->instantiate($castClass);

        if (!$cast instanceof CastInterface) {
            throw EntityException::castClassInvalid($cast::class, "Resolving cast '$castClass'");
        }

        return $this->casts[$castClass] = $cast;
    }

    /**
     * @param class-string<CastInterface> $castClass
     *
     * @throws EntityException
     */
    private function resolveFromContainer(
        string $castClass,
    ): object {
        try {
            return $this->container->get($castClass);
        } catch (Throwable $e) {
            throw EntityException::castResolutionFailed($castClass, $e);
        }
    }

    /**
     * @param class-string<CastInterface> $castClass
     *
     * @throws EntityException|ReflectionException
     */
    private function instantiate(
        string $castClass,
    ): CastInterface {
        $constructor = new ReflectionClass($castClass)->getConstructor();

        if (
            $constructor !== null
            && $constructor->getNumberOfRequiredParameters() > 0
        ) {
            throw EntityException::castRequiresContainer($castClass);
        }

        return new $castClass();
    }
}
